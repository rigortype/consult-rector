<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Console;

use Symfony\Component\Console\Application as BaseApplication;
use TypedDuck\ConsultRector\Command\ApplyCommand;
use TypedDuck\ConsultRector\Command\AstCommand;
use TypedDuck\ConsultRector\Command\DocCommand;
use TypedDuck\ConsultRector\Command\DryRunCommand;
use TypedDuck\ConsultRector\Command\PhpStanCommand;
use TypedDuck\ConsultRector\Command\SearchCommand;

/**
 * The consult-rector CLI: a shell-first helper that assembles temporary Rector
 * configs, runs Rector, and returns structured results (ADR-0001).
 *
 * Both interface paths — the slash-command skill and the MCP server — invoke
 * this same binary (ADR-0002).
 */
final class Application extends BaseApplication
{
    public const NAME = 'consult-rector';

    public const VERSION = '0.0.1';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->addCommands([
            new SearchCommand(),
            new DryRunCommand(),
            new ApplyCommand(),
            new AstCommand(),
            new DocCommand(),
            new PhpStanCommand(),
        ]);
    }
}
