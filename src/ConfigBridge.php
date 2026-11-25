<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Writes stored values into Laravel's configuration and can undo it, so a
 * long-running process does not keep a value that is no longer stored.
 *
 * @internal
 */
class ConfigBridge {
    /** @var array<string, array{original: mixed, applied: mixed}> */
    protected array $written = [];

    public function write(Config $config, string $path, mixed $value): void {
        $current = $config->get($path);

        // Keep the first original, unless something else changed the path since.
        $original = isset($this->written[$path]) && $this->written[$path]['applied'] === $current
            ? $this->written[$path]['original']
            : $current;

        $this->written[$path] = ['original' => $original, 'applied' => $value];
        $config->set($path, $value);
    }

    public function restore(Config $config, string $path): void {
        if (!isset($this->written[$path])) {
            return;
        }

        if ($this->written[$path]['applied'] === $config->get($path)) {
            $config->set($path, $this->written[$path]['original']);
        }

        unset($this->written[$path]);
    }
}
