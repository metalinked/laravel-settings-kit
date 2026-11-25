<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Support\Facades\Lang;

/**
 * The package's own error messages, from lang/{locale}/messages.php.
 *
 * @internal
 */
final class Messages {
    /**
     * @param array<string, int|string> $replace
     */
    public static function get(string $line, array $replace = []): string {
        $key = "settings-kit::messages.{$line}";

        // English when neither the current nor the fallback locale has the line.
        $text = Lang::get($key, $replace, Lang::has($key) ? null : 'en');

        return is_string($text) ? $text : $key;
    }
}
