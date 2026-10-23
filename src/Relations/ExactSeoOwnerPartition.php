<?php

declare(strict_types=1);

namespace Nvl\Seo\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Partitions eager limits by exact foreign identifier bytes under native collations.
 *
 * @internal
 */
final readonly class ExactSeoOwnerPartition implements Expression
{
    /** Retain the grammar-owned foreign column and its connection dialect. */
    public function __construct(private string $column, private string $driver) {}

    /** Compile an exact partition without changing the selected owner identifier. */
    public function getValue(Grammar $grammar): string
    {
        $column = $grammar->wrap($this->column);

        return match ($this->driver) {
            'mysql', 'mariadb' => "BINARY {$column}",
            'pgsql' => "{$column} COLLATE \"C\"",
            'sqlsrv' => "CAST({$column} AS VARBINARY(MAX))",
            default => "CAST({$column} AS BLOB)",
        };
    }
}
