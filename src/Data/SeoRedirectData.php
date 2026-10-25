<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Spatie\LaravelData\Data;

/**
 * Management snapshot carrying the redirect's optimistic revision.
 *
 * @api
 */
final class SeoRedirectData extends Data
{
    /** @param array<string, mixed>|null $metadata */
    public function __construct(
        public readonly string $id,
        public readonly string $scope,
        public readonly ?string $locale,
        public readonly string $source,
        public readonly string $target,
        public readonly int $statusCode,
        public readonly int $revision,
        public readonly bool $isActive,
        public readonly ?string $expiresAt,
        public readonly ?array $metadata,
    ) {}
}
