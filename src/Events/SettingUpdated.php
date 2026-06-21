<?php

namespace Metalinked\LaravelSettingsKit\Events;

class SettingUpdated {
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly ?int $userId,
    ) {
    }
}
