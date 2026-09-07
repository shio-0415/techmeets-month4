<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
        'not_operator_with_successor_space' => true,
        'blank_line_after_namespace' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'declare_strict_types' => false,
    ])
    ->setFinder($finder);
