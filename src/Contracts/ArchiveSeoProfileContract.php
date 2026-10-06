<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Models\SeoProfile;

/**
 * Defines the consumer-facing ArchiveSeoProfileAction workflow.
 *
 * @api
 */
interface ArchiveSeoProfileContract
{
    /**
     * Execute the execute workflow.
     */
    public function execute(
        SeoProfile|string $profile,
        bool $archived,
        int $expectedRevision,
    ): SeoProfile;
}
