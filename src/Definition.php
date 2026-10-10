<?php

namespace Metalinked\LaravelSettingsKit;

use BackedEnum;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Metalinked\LaravelSettingsKit\Exceptions\InvalidSettingValue;
use Stringable;

/**
 * Describes a single setting: its type, default value, who may override it and
 * how it is presented. Definitions live in code; only values are stored.
 */
class Definition {
    public const TYPES = ['string', 'boolean', 'integer', 'float', 'array', 'select', 'enum'];

    /** Internal type used for keys that were never defined (non-strict mode). */
    protected const UNTYPED = 'mixed';

    protected const ARRAY_KEYS = [
        'type', 'default', 'options', 'enum', 'overridable', 'group', 'label',
        'description', 'rules', 'nullable', 'encrypted', 'meta',
    ];

    protected string $type = 'string';
    protected bool $typeIsExplicit = false;
    protected mixed $default = null;

    /** @var list<int|string> */
    protected array $options = [];

    /** @var array<string, string> option value => label (or translation key) */
    protected array $optionLabels = [];

    /** @var class-string<BackedEnum>|null */
    protected ?string $enum = null;

    /** @var bool|list<class-string> */
    protected bool|array $overridable = false;

    protected ?string $group = null;
    protected ?string $label = null;
    protected ?string $description = null;
    protected array $rules = [];
    protected bool $nullable = false;
    protected bool $encrypted = false;
    protected array $meta = [];
    protected bool $defined = true;

    final public function __construct(public readonly string $key) {
        if ($key === '') {
            throw new InvalidArgumentException('A setting key cannot be empty.');
        }
    }

    /**
     * Build a definition from its config-file form: either a default value
     * (type inferred) or an array of options.
     */
    public static function fromConfig(string $key, mixed $config): static {
        $definition = new static($key);

        if (!is_array($config)) {
            return $definition->default($config);
        }

        if ($unknown = array_diff(array_keys($config), self::ARRAY_KEYS)) {
            throw new InvalidArgumentException("Unknown option(s) [" . implode(', ', $unknown) . "] in the definition of setting '{$key}'.");
        }

        $type = $config['type'] ?? (isset($config['enum']) ? 'enum' : (isset($config['options']) ? 'select' : null));

        match ($type) {
            null => null,
            'select' => $definition->select($config['options'] ?? []),
            'enum' => $definition->enum($config['enum'] ?? ''),
            default => $definition->type($type),
        };

        if (array_key_exists('default', $config)) {
            $definition->default($config['default']);
        }

        $overridable = $config['overridable'] ?? false;
        is_array($overridable) ? $definition->overridable(...$overridable) : $definition->overridable($overridable);

        foreach (['group', 'label', 'description'] as $option) {
            if (isset($config[$option])) {
                $definition->{$option}($config[$option]);
            }
        }

        return $definition
            ->rules($config['rules'] ?? [])
            ->nullable($config['nullable'] ?? false)
            ->encrypted($config['encrypted'] ?? false)
            ->meta($config['meta'] ?? []);
    }

    /**
     * An untyped definition for a key that was never declared (non-strict mode).
     */
    public static function undefined(string $key): static {
        $definition = new static($key);
        $definition->type = self::UNTYPED;
        $definition->typeIsExplicit = true;
        $definition->overridable = true;
        $definition->nullable = true;
        $definition->defined = false;

        return $definition;
    }

    // ------------------------------------------------------------------
    // Fluent builder
    // ------------------------------------------------------------------

    public function type(string $type): static {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Invalid type '{$type}' for setting '{$this->key}'. Allowed: " . implode(', ', self::TYPES) . '.');
        }

        if ($type === 'select' && $this->options === []) {
            throw new InvalidArgumentException("Use select([...]) to give setting '{$this->key}' its options.");
        }

        if ($type === 'enum' && $this->enum === null) {
            throw new InvalidArgumentException("Use enum(MyEnum::class) to give setting '{$this->key}' its enum.");
        }

        $this->type = $type;
        $this->typeIsExplicit = true;

        return $this;
    }

    public function string(): static {
        return $this->type('string');
    }

    public function boolean(): static {
        return $this->type('boolean');
    }

    public function integer(): static {
        return $this->type('integer');
    }

    public function float(): static {
        return $this->type('float');
    }

    public function array(): static {
        return $this->type('array');
    }

    /**
     * Restrict the value to a fixed list. Pass a list of values, or an
     * associative array of value => label (label may be a translation key).
     *
     * @param array<int|string, mixed> $options
     */
    public function select(array $options): static {
        if ($options === []) {
            throw new InvalidArgumentException("Setting '{$this->key}' needs at least one option.");
        }

        if (array_is_list($options)) {
            $this->options = $options;
            $this->optionLabels = [];
        } else {
            $this->options = array_keys($options);
            $this->optionLabels = array_combine(array_map('strval', array_keys($options)), array_map('strval', $options));
        }

        return $this->type('select');
    }

    /**
     * @param class-string<BackedEnum> $class
     */
    public function enum(string $class): static {
        if (!is_subclass_of($class, BackedEnum::class)) {
            throw new InvalidArgumentException("Setting '{$this->key}' must use a backed enum, [{$class}] given.");
        }

        $this->enum = $class;

        return $this->type('enum');
    }

    /**
     * Set the default value. If no type was given yet, it is inferred from it.
     */
    public function default(mixed $value): static {
        if (!$this->typeIsExplicit) {
            match (true) {
                is_bool($value) => $this->type = 'boolean',
                is_int($value) => $this->type = 'integer',
                is_float($value) => $this->type = 'float',
                is_array($value) => $this->type = 'array',
                $value instanceof BackedEnum => $this->enum($value::class),
                default => $this->type = 'string',
            };
        }

        $this->default = $value;

        return $this;
    }

    /**
     * Allow models to override this setting. With no arguments any model may;
     * otherwise only instances of the given classes.
     */
    public function overridable(bool|string ...$models): static {
        if ($models === [] || $models === [true]) {
            $this->overridable = true;
        } elseif ($models === [false]) {
            $this->overridable = false;
        } else {
            $this->overridable = array_values(array_filter($models, 'is_string'));
        }

        return $this;
    }

    public function group(string $group): static {
        $this->group = $group;

        return $this;
    }

    public function label(string $label): static {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): static {
        $this->description = $description;

        return $this;
    }

    /**
     * Extra Laravel validation rules applied after type checking.
     */
    public function rules(array|string $rules): static {
        $this->rules = is_string($rules) ? explode('|', $rules) : $rules;

        return $this;
    }

    public function nullable(bool $nullable = true): static {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Store the value encrypted with the application key (API keys, secrets…).
     */
    public function encrypted(bool $encrypted = true): static {
        $this->encrypted = $encrypted;

        return $this;
    }

    /**
     * Free-form data for your UI (icon, input hint, order…). Exposed by panel().
     */
    public function meta(array $meta): static {
        $this->meta = $meta;

        return $this;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function isDefined(): bool {
        return $this->defined;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getDefault(): mixed {
        if ($this->default === null) {
            return null;
        }

        try {
            return $this->normalize($this->default);
        } catch (InvalidSettingValue) {
            throw new InvalidArgumentException("The default value of setting '{$this->key}' does not match its type ({$this->type}).");
        }
    }

    public function getGroup(): string {
        return $this->group ?? (str_contains($this->key, '.') ? Str::before($this->key, '.') : 'general');
    }

    public function getRules(): array {
        return $this->rules;
    }

    public function getMeta(): array {
        return $this->meta;
    }

    /** @return class-string<BackedEnum>|null */
    public function getEnum(): ?string {
        return $this->enum;
    }

    public function isNullable(): bool {
        return $this->nullable;
    }

    public function isEncrypted(): bool {
        return $this->encrypted;
    }

    public function isOverridable(): bool {
        return $this->overridable !== false;
    }

    /**
     * Whether a model of the given class may hold its own value.
     */
    public function isOverridableBy(?string $class): bool {
        if (is_bool($this->overridable)) {
            return $this->overridable;
        }

        if ($class === null) {
            return false;
        }

        foreach ($this->overridable as $allowed) {
            if (is_a($class, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    public function getLabel(): string {
        if ($this->label !== null) {
            return (string) __($this->label);
        }

        return $this->translate('label') ?? Str::headline(Str::afterLast($this->key, '.'));
    }

    public function getDescription(): ?string {
        if ($this->description !== null) {
            return (string) __($this->description);
        }

        return $this->translate('description');
    }

    /**
     * Allowed values with human-readable labels (select and enum types).
     *
     * @return list<array{value: int|string, label: string}>
     */
    public function getOptions(): array {
        if ($this->type === 'enum' && $this->enum !== null) {
            return array_map(fn (BackedEnum $case) => [
                'value' => $case->value,
                'label' => $this->translate("options.{$case->value}")
                    ?? (method_exists($case, 'label') ? (string) $case->label() : Str::headline($case->name)),
            ], ($this->enum)::cases());
        }

        if ($this->type === 'select') {
            return array_map(fn ($value) => [
                'value' => $value,
                'label' => isset($this->optionLabels[(string) $value])
                    ? (string) __($this->optionLabels[(string) $value])
                    : ($this->translate("options.{$value}") ?? Str::headline((string) $value)),
            ], $this->options);
        }

        return [];
    }

    // ------------------------------------------------------------------
    // Values
    // ------------------------------------------------------------------

    /**
     * Coerce an incoming value to this setting's PHP type, or fail.
     * Lenient with strings ("1", "true", "42") so HTTP input works directly.
     *
     * @throws InvalidSettingValue
     */
    public function normalize(mixed $value): mixed {
        if ($value === null) {
            return $this->nullable ? null : throw new InvalidSettingValue("The {$this->key} setting cannot be null.");
        }

        return match ($this->type) {
            self::UNTYPED => $value,
            'string' => is_scalar($value) || $value instanceof Stringable
                ? (string) $value
                : $this->fail('must be a string'),
            'boolean' => is_bool($value)
                ? $value
                : (is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null)
                    ?? $this->fail('must be true or false'),
            'integer' => is_int($value)
                ? $value
                : (is_scalar($value) && !is_bool($value) && ($int = filter_var($value, FILTER_VALIDATE_INT)) !== false
                    ? $int
                    : $this->fail('must be an integer')),
            'float' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
                ? (float) $value
                : $this->fail('must be a number'),
            'array' => is_array($value) ? $value : $this->fail('must be an array'),
            'select' => $this->matchOption($value),
            'enum' => $this->matchEnum($value),
            default => $this->fail("has an unknown type '{$this->type}'"),
        };
    }

    /**
     * Serialise a normalised value for storage.
     */
    public function encode(mixed $value): string {
        $json = json_encode(
            $value instanceof BackedEnum ? $value->value : $value,
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE
        );

        return $this->encrypted ? Crypt::encryptString($json) : $json;
    }

    /**
     * Turn a stored value back into its PHP type. Values that no longer fit the
     * definition (type changed, option removed, key rotated) yield $fallback.
     */
    public function decode(string $raw, mixed $fallback = null): mixed {
        try {
            $json = $this->encrypted ? Crypt::decryptString($raw) : $raw;

            return $this->normalize(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } catch (InvalidSettingValue|DecryptException|\JsonException) {
            return $fallback;
        }
    }

    /**
     * Plain JSON-friendly representation (enums become their backing value).
     */
    public static function export(mixed $value): mixed {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    protected function matchOption(mixed $value): int|string {
        $value = $value instanceof BackedEnum ? $value->value : $value;

        if (is_scalar($value)) {
            foreach ($this->options as $option) {
                if ((string) $option === (string) $value) {
                    return $option;
                }
            }
        }

        return $this->fail('must be one of: ' . implode(', ', $this->options));
    }

    protected function matchEnum(mixed $value): BackedEnum {
        /** @var class-string<BackedEnum> $enum */
        $enum = $this->enum;

        if ($value instanceof $enum) {
            return $value;
        }

        $cases = ($enum)::cases();

        if (is_int($value) || is_string($value)) {
            foreach ($cases as $case) {
                if ((string) $case->value === (string) $value) {
                    return $case;
                }
            }
        }

        return $this->fail('must be one of: ' . implode(', ', array_map(fn ($case) => $case->value, $cases)));
    }

    protected function translate(string $suffix): ?string {
        $key = config('settings-kit.translations', 'settings') . ".{$this->key}.{$suffix}";

        return Lang::has($key) ? (string) __($key) : null;
    }

    protected function fail(string $reason): never {
        throw new InvalidSettingValue("The {$this->key} setting {$reason}.");
    }
}
