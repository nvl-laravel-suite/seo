<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\ResolvedSeoData;
use Nvl\Seo\Models\SeoProfile;

/**
 * Defines the consumer-facing PreviewSeoProfileAction workflow.
 *
 * @api
 */
interface PreviewSeoProfileContract
{
    /**
     * Execute the execute workflow.
     */
    public function execute(SeoProfile $profile, ?string $locale = null): ResolvedSeoData;
}
