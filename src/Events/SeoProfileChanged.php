<?php

declare(strict_types=1);

namespace Nvl\Seo\Events;

use Nvl\Support\Contracts\DomainEvent;

/**
 * Announces a durable SEO profile mutation.
 *
 * @api
 */
final class SeoProfileChanged implements DomainEvent
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $scope,
        public readonly string $operation,
        public readonly int $schemaVersion = 1,
    ) {}

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
