<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/bin',
    ])
    ->withPreparedSets(
        arrays: true,
        namespaces: true,
        spaces: true,
        docblocks: true,
        comments: true,
    )
    ->withRules([
        OrderedImportsFixer::class,
        NoUnusedImportsFixer::class,
    ]);
