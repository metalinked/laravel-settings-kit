<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => false,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        // Configuration for K&R style (opening braces on same line) - modern rules
        'control_structure_braces' => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => [
            'functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
            'anonymous_classes_opening_brace' => 'same_line',
        ],
        'statement_indentation' => true,
        'no_extra_blank_lines' => ['tokens' => ['curly_brace_block']],
    ])
    ->setLineEnding("\n");
