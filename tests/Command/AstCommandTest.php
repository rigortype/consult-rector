<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TypedDuck\ConsultRector\Command\AstCommand;
use TypedDuck\ConsultRector\Console\Application;
use TypedDuck\ConsultRector\PhpStan\PhpStanRunner;
use TypedDuck\ConsultRector\PhpStan\Verifier;

#[CoversClass(AstCommand::class)]
#[UsesClass(Application::class)]
#[UsesClass(PhpStanRunner::class)]
#[UsesClass(Verifier::class)]
final class AstCommandTest extends TestCase
{
    public function testRejectsAnUnknownEngine(): void
    {
        $tester = new CommandTester((new Application())->find('ast'));
        $status = $tester->execute([
            'path' => __FILE__,
            'dsl' => '["add-import", ["class", "App\\\\Foo"]]',
            '--engine' => 'psalm',
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('--engine must be', $tester->getDisplay());
    }
}
