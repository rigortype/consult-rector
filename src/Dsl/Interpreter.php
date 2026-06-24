<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl;

/**
 * Turns a decoded AST DSL S-expression into an ordered list of {@see CompiledRule}
 * (ADR-0005). A node is `[name, [key, value], ...]`; `chain` recurses over its
 * sub-nodes, preserving order so dependent steps run in sequence.
 */
final class Interpreter
{
    public function __construct(
        private readonly TransformResolver $resolver = new TransformResolver()
    )
    {
    }

    /**
     * @return list<CompiledRule>
     */
    public function interpret(mixed $dsl): array
    {
        [$name, $rest] = $this->splitNode($dsl);

        if ($name === 'chain') {
            return $this->interpretChain($rest);
        }

        return [$this->resolver->resolve($name)->compile($this->parsePairs($name, $rest))];
    }

    /**
     * @param list<mixed> $subNodes
     *
     * @return list<CompiledRule>
     */
    private function interpretChain(array $subNodes): array
    {
        $compiled = [];
        foreach ($subNodes as $subNode) {
            foreach ($this->interpret($subNode) as $rule) {
                $compiled[] = $rule;
            }
        }

        if ($compiled === []) {
            throw new DslException('chain: at least one sub-transform is required.');
        }

        return $compiled;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function splitNode(mixed $dsl): array
    {
        if (! is_array($dsl) || ! array_is_list($dsl) || $dsl === []) {
            throw new DslException('A DSL node must be a non-empty JSON array.');
        }

        $name = $dsl[0] ?? null;
        if (! is_string($name) || $name === '') {
            throw new DslException('A DSL node must start with a transform name (string).');
        }

        return [$name, array_slice($dsl, 1)];
    }

    /**
     * @param list<mixed> $pairs
     *
     * @return array<string, mixed>
     */
    private function parsePairs(string $transform, array $pairs): array
    {
        $arguments = [];
        foreach ($pairs as $pair) {
            if (! is_array($pair) || ! array_is_list($pair) || count($pair) !== 2) {
                throw new DslException(sprintf('%s: each argument must be a ["key", value] pair.', $transform));
            }

            [$key, $value] = $pair;
            if (! is_string($key)) {
                throw new DslException(sprintf('%s: argument keys must be strings.', $transform));
            }

            $arguments[$key] = $value;
        }

        return $arguments;
    }
}
