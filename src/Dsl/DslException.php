<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Dsl;

use RuntimeException;

/**
 * Raised when an AST DSL S-expression is structurally invalid or names an
 * unknown / misconfigured transform.
 */
final class DslException extends RuntimeException
{
}
