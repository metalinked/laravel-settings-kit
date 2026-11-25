<?php

namespace Metalinked\LaravelSettingsKit\Exceptions;

use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Messages;

/**
 * A value does not fit its setting's type. Surfaced to callers as a
 * ValidationException keyed by the setting key.
 */
class InvalidSettingValue extends InvalidArgumentException {
    protected ?string $messageLine = null;

    /** @var array<string, int|string> */
    protected array $replace = [];

    /**
     * @param array<string, int|string> $replace
     */
    public static function translated(string $messageLine, array $replace): self {
        $exception = new self(Messages::get($messageLine, $replace));
        $exception->messageLine = $messageLine;
        $exception->replace = $replace;

        return $exception;
    }

    /**
     * The line of the package's messages file, if the message comes from one.
     */
    public function messageLine(): ?string {
        return $this->messageLine;
    }

    /**
     * The same message, calling the setting by another name (such as its label).
     */
    public function messageFor(string $name): string {
        return $this->messageLine === null
            ? $this->getMessage()
            : Messages::get($this->messageLine, ['key' => $name] + $this->replace);
    }
}
