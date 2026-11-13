<?php

declare(strict_types=1);

namespace Nvl\Seo\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Compares one grammar-owned host identity column with two bound copies of its exact value.
 *
 * @internal
 */
final readonly class ExactSeoOwnerText implements Expression
{
    /** Retain the identifier for grammar quoting and the actual connection dialect. */
    public function __construct(private string $column, private string $driver) {}

    /** Preserve byte identity and length independently of case or padding collation rules. */
    public function getValue(Grammar $grammar): string
    {
        $column = $grammar->wrap($this->column);

        return match ($this->driver) {
            'pgsql' => "{$column} COLLATE \"C\" = CAST(? AS TEXT) COLLATE \"C\" AND OCTET_LENGTH({$column}) = OCTET_LENGTH(CAST(? AS TEXT))",
            default => "{$column} = ? COLLATE BINARY AND LENGTH(CAST({$column} AS BLOB)) = LENGTH(CAST(? AS BLOB))",
        };
    }
}
