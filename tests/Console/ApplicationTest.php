<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Console\Application;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    public function testRegistersTheDocumentedSubcommands(): void
    {
        $application = new Application();

        foreach (['search', 'dry-run', 'apply', 'ast', 'doc', 'phpstan'] as $name) {
            self::assertTrue($application->has($name), "missing command: {$name}");
        }
    }
}
