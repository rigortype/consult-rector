<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Mcp\RectorTools;

/**
 * Integration: boot the real MCP server over stdio and confirm `tools/list`
 * advertises every consult-rector tool (ADR-0003).
 */
#[CoversClass(RectorTools::class)]
final class McpServerTest extends TestCase
{
    public function testToolsListAdvertisesEveryRectorTool(): void
    {
        $handshake = implode("\n", [
            '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"test","version":"1"}}}',
            '{"jsonrpc":"2.0","method":"notifications/initialized"}',
            '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}',
            '',
        ]);

        $stdout = $this->runServer($handshake);

        foreach (RectorTools::definitions() as $definition) {
            self::assertStringContainsString('"name":"' . $definition['name'] . '"', $stdout);
        }
    }

    private function runServer(string $stdin): string
    {
        $binary = \dirname(__DIR__, 2) . '/bin/consult-rector-mcp';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = proc_open([PHP_BINARY, $binary], $descriptors, $pipes);
        self::assertIsResource($process);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        proc_close($process);

        return is_string($stdout) ? $stdout : '';
    }
}
