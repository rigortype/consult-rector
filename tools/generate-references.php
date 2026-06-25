<?php

declare(strict_types=1);

/**
 * Maintenance script (dev-only): regenerate the auto-generated reference docs
 * from the installed Rector source. The hand-curated `recipe-book.md` is left
 * untouched.
 *
 *     php tools/generate-references.php
 */

use TypedDuck\ConsultRector\Rector\RuleCatalog;
use TypedDuck\ConsultRector\Reference\ReferenceGenerator;
use TypedDuck\ConsultRector\Reference\RuleIntrospector;

require __DIR__ . '/../vendor/autoload.php';

$fqcns = RuleCatalog::fromInstalledRector()->search('');

$introspector = new RuleIntrospector();
$descriptors = array_map(static fn (string $fqcn) => $introspector->describe($fqcn), $fqcns);

$generator = new ReferenceGenerator();
$references = __DIR__ . '/../references';

file_put_contents($references . '/rectors-by-category.md', $generator->byCategory($descriptors) . "\n");
file_put_contents($references . '/rectors-compendium.md', $generator->compendium($descriptors) . "\n");

fwrite(STDOUT, sprintf("Regenerated references for %d rules.\n", count($descriptors)));
