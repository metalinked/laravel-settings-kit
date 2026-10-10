<?php

namespace Metalinked\LaravelSettingsKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Who a stored value belongs to: the application (global) or one model.
 * Global rows use empty strings rather than NULL so the unique index holds.
 */
final class Scope {
    private function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {
    }

    public static function global(): self {
        return new self('', '');
    }

    public static function of(Model|string $settable, int|string|null $id = null): self {
        if ($settable instanceof Model) {
            if ($settable->getKey() === null) {
                throw new InvalidArgumentException('Settings can only be scoped to a saved model.');
            }

            return new self($settable->getMorphClass(), (string) $settable->getKey());
        }

        if ($settable === '' || $id === null || $id === '') {
            throw new InvalidArgumentException('A settings scope needs a model type and id.');
        }

        return new self($settable, (string) $id);
    }

    public function isGlobal(): bool {
        return $this->type === '';
    }

    /**
     * The model class behind the morph type (respects Relation::morphMap()).
     */
    public function modelClass(): ?string {
        return $this->isGlobal() ? null : (Relation::getMorphedModel($this->type) ?? $this->type);
    }

    public function cacheSuffix(): string {
        return $this->isGlobal() ? 'global' : sha1("{$this->type}|{$this->id}");
    }
}
