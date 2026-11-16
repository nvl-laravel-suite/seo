<?php

declare(strict_types=1);

namespace Nvl\Seo\Events;

use Nvl\Support\Contracts\DomainEvent;

/**
 * Announces a durable SEO profile mutation.
 *
 * @api
 *
 * @phpstan-type PathSnapshots array<string, array{id:string,path:string|null}>
 */
final class SeoProfileChanged implements DomainEvent
{
    /**
     * Capture owner and localized path facts on the writer connection before commit.
     *
     * @param  PathSnapshots  $previousTranslations
     * @param  PathSnapshots  $currentTranslations
     */
    public function __construct(
        public readonly string $profileId,
        public readonly string $scope,
        public readonly string $operation,
        public readonly int $schemaVersion = 1,
        public readonly ?string $ownerType = null,
        public readonly ?string $ownerId = null,
        public readonly array $previousTranslations = [],
        public readonly array $currentTranslations = [],
    ) {}

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
