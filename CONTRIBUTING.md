# Contributing to Laravel Settings Kit

Thank you for your interest in contributing to Laravel Settings Kit! This guide will help you get started.

## Code Style

We use PHP-CS-Fixer with a **K&R style** configuration (opening braces on the same line).

### Formatting Rules

- **Opening braces** on the same line as the declaration
- **PSR-12 compliance** with K&R modifications
- **Short array syntax** (`[]` instead of `array()`)
- **Alphabetical imports** ordering
- **Trailing commas** in multiline arrays

### Example Code Style

```php
class ExampleClass {
    public function exampleMethod($param1, $param2) {
        if ($condition) {
            return [
                'key1' => 'value1',
                'key2' => 'value2',
            ];
        }
        
        foreach ($items as $item) {
            // Process item
        }
        
        return $result;
    }
}
```

## Development Commands

### Code Formatting
```bash
# Apply code formatting
composer format

# Check formatting without applying changes
composer format-check
```

### Testing
```bash
# Run all tests
composer test

# Static analysis
composer analyse

# Run all quality checks
composer quality
```

## Pull Request Process

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Format** your code (`composer format`)
4. **Test** your changes (`composer test`)
5. **Analyze** code quality (`composer analyse`)
6. **Commit** with semantic commit messages:
   - `feat:` for new features
   - `fix:` for bug fixes
   - `docs:` for documentation changes
   - `style:` for formatting changes
   - `refactor:` for code refactoring
   - `test:` for adding tests
7. **Push** to your branch
8. **Create** a Pull Request

## Code Quality Requirements

All Pull Requests must pass:

- ✅ **All tests** must pass (`composer test`)
- ✅ **Code formatting** must be applied (`composer format-check`)
- ✅ **Static analysis** must pass (`composer analyse`)
- ✅ **No merge conflicts** with main branch

## Testing

- Write tests for new features
- Ensure existing tests pass
- Aim for good test coverage
- Use descriptive test method names

## Documentation

- Update README.md if needed
- Add PHPDoc comments for new methods
- Include usage examples for new features

## Questions?

Feel free to open an issue for any questions about contributing!
