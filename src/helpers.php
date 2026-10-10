<?php

use Metalinked\LaravelSettingsKit\SettingsManager;

if (!function_exists('settings')) {
    /**
     * settings()                 → the manager
     * settings('key', $default)  → a global value
     * settings(['key' => $v])    → store global values
     */
    function settings(string|array|null $key = null, mixed $default = null): mixed {
        $manager = app(SettingsManager::class);

        if ($key === null) {
            return $manager;
        }

        if (is_array($key)) {
            $manager->set($key);

            return null;
        }

        return $manager->get($key, $default);
    }
}
