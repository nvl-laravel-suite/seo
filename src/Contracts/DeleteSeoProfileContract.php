<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Models\SeoProfile;

/**
 * Defines the consumer-facing DeleteSeoProfileAction workflow.
 *
 * @api
 */
interface DeleteSeoProfileContract
{
    /**
     * Execute the execute workflow.
     */
    public function execute(SeoProfile|string $profile, ?int $expectedRevision = null): bool;
}
