<?php

namespace Metalinked\LaravelSettingsKit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Metalinked\LaravelSettingsKit\Definition;
use Metalinked\LaravelSettingsKit\SettingsManager;
use Metalinked\LaravelSettingsKit\SettingsRepository;

class EncryptSettingsCommand extends Command {
    protected $signature = 'settings:encrypt
        {--decrypt : Also store as plain text the encrypted values of settings that are no longer encrypted}
        {--dry-run : Show what would change without saving it}';

    protected $description = 'Encrypt stored values with the current APP_KEY, as their definitions ask';

    /** @var array{encrypted: int, re-encrypted: int, decrypted: int} */
    protected array $counts = ['encrypted' => 0, 're-encrypted' => 0, 'decrypted' => 0];

    /** @var array<string, int> key => values that cannot be read */
    protected array $unreadable = [];

    /** @var array<string, int> key => encrypted values of a setting that is not */
    protected array $leftEncrypted = [];

    public function handle(SettingsManager $settings, SettingsRepository $repository): int {
        $dryRun = (bool) $this->option('dry-run');
        $this->startFromZero();

        foreach ($settings->definitions() as $key => $definition) {
            $repository->rewrite((string) $key, function (string $raw) use ($definition, $dryRun): ?string {
                $new = $this->convert($definition, $raw);

                return $dryRun ? null : $new;
            });
        }

        foreach ($this->leftEncrypted as $key => $total) {
            $this->components->warn("{$key}: {$total} value(s) are encrypted but the setting is not. Use --decrypt to store them as plain text.");
        }

        foreach ($this->unreadable as $key => $total) {
            $this->components->error("{$key}: {$total} value(s) cannot be read. Check APP_PREVIOUS_KEYS, or save the setting again.");
        }

        $summary = "{$this->counts['encrypted']} encrypted, {$this->counts['re-encrypted']} re-encrypted, {$this->counts['decrypted']} decrypted";
        $this->components->info($dryRun ? "Dry run, nothing was saved: {$summary}." : "Stored values: {$summary}.");

        // A failure tells a deploy script that an old key may still be needed.
        return $this->unreadable === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The console keeps one instance of a command between calls.
     */
    protected function startFromZero(): void {
        $this->counts = ['encrypted' => 0, 're-encrypted' => 0, 'decrypted' => 0];
        $this->unreadable = [];
        $this->leftEncrypted = [];
    }

    /**
     * What a stored value must become, or null to leave it as it is.
     */
    protected function convert(Definition $definition, string $raw): ?string {
        // Plain values are stored as JSON; Laravel's encrypted payloads are not JSON.
        if ($this->isJson($raw)) {
            if (!$definition->isEncrypted()) {
                return null;
            }

            $this->counts['encrypted']++;

            return Crypt::encryptString($raw);
        }

        try {
            $json = Crypt::decryptString($raw);
        } catch (DecryptException) {
            $json = null;
        }

        if ($json === null || !$this->isJson($json)) {
            $this->unreadable[$definition->key] = ($this->unreadable[$definition->key] ?? 0) + 1;

            return null;
        }

        if ($definition->isEncrypted()) {
            $this->counts['re-encrypted']++;

            return Crypt::encryptString($json);
        }

        if (!$this->option('decrypt')) {
            $this->leftEncrypted[$definition->key] = ($this->leftEncrypted[$definition->key] ?? 0) + 1;

            return null;
        }

        $this->counts['decrypted']++;

        return $json;
    }

    protected function isJson(string $value): bool {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
