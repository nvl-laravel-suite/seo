<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\SeoProfileData;
use Nvl\Seo\Models\SeoProfile;

/**
 * Defines the consumer-facing GetSeoProfileAction workflow.
 *
 * @api
 */
interface GetSeoProfileContract
{
    /**
     * Return one complete stable management profile.
     */
    public function execute(SeoProfile|string $profile): SeoProfileData;
}
