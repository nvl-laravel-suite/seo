<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Spatie\LaravelData\Data;

/**
 * Runtime redirect decision after chain flattening and expiry checks.
 *
 * @api
 */
final class ResolvedRedirectData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $target,
        public readonly int $statusCode,
    ) {}
}
