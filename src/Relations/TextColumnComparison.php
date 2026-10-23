<?php

declare(strict_types=1);

namespace Nvl\Seo\Relations;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Internal expression comparing two grammar-wrapped package-owned columns.
 */
final readonly class TextColumnComparison implements Expression
{
    public function __construct(
        private string $left,
        private string $right,
        private string $driver,
    ) {}

    public function getValue(Grammar $grammar): string
    {
        $left = $this->left;
        $right = $this->right;

        return match ($this->driver) {
            'mysql', 'mariadb' => "BINARY {$left} = BINARY {$right} AND OCTET_LENGTH({$left}) = OCTET_LENGTH({$right})",
            'pgsql' => "{$left} COLLATE \"C\" = {$right} COLLATE \"C\" AND OCTET_LENGTH({$left}) = OCTET_LENGTH({$right})",
            'sqlsrv' => "{$left} COLLATE Latin1_General_100_BIN2 = {$right} COLLATE Latin1_General_100_BIN2 AND DATALENGTH({$left}) = DATALENGTH({$right})",
            default => "{$left} = {$right} COLLATE BINARY AND LENGTH(CAST({$left} AS BLOB)) = LENGTH(CAST({$right} AS BLOB))",
        };
    }
}
