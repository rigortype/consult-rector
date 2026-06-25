<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use Composer\InstalledVersions;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Keyword search over the installed Rector rule catalogue (CONTEXT.md "Rule
 * query"). Rector's `list-rules` only reports rules registered in an active
 * config, so the full catalogue is discovered by scanning the rule classes under
 * the package's `rules/` directory (PSR-4 `Rector\` → `rules/`) and deriving
 * each FQCN from its path. Abstract base classes are skipped.
 */
final class RuleCatalog
{
    private readonly string $rulesDirectory;

    public function __construct(string $rulesDirectory)
    {
        $this->rulesDirectory = rtrim($rulesDirectory, '/\\');
    }

    public static function fromInstalledRector(): self
    {
        $path = InstalledVersions::getInstallPath('rector/rector');
        if ($path === null) {
            throw new RuntimeException('rector/rector is not installed.');
        }

        return new self($path . '/rules');
    }

    /**
     * Keyword search over the catalogue. Multiple keywords narrow the result: a
     * rule matches only when its FQCN contains every (case-insensitive) keyword, so
     * `search('trait', 'rename')` finds rules mentioning both. Empty/whitespace
     * keywords are ignored; with no effective keyword, every rule matches.
     *
     * @return list<string> matching rule FQCNs, sorted
     */
    public function search(string ...$keywords): array
    {
        $needles = [];
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $needles[] = $keyword;
            }
        }

        $matches = [];
        foreach ($this->allRules() as $fqcn) {
            if ($this->matchesAll($fqcn, $needles)) {
                $matches[] = $fqcn;
            }
        }

        sort($matches);

        return $matches;
    }

    /**
     * @param list<string> $needles
     */
    private function matchesAll(string $fqcn, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (stripos($fqcn, $needle) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function allRules(): array
    {
        if (! is_dir($this->rulesDirectory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rulesDirectory, FilesystemIterator::SKIP_DOTS),
        );

        $rules = [];
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $name = $file->getBasename('.php');
            if (! str_ends_with($name, 'Rector') || str_starts_with($name, 'Abstract')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($this->rulesDirectory) + 1, -4);
            $rules[] = 'Rector\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }

        return $rules;
    }
}
