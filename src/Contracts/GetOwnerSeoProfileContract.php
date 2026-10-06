<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Data\SeoProfileData;

/**
 * Defines the consumer-facing GetOwnerSeoProfileAction workflow.
 *
 * @api
 */
interface GetOwnerSeoProfileContract
{
    /**
     * Return the complete profile projection, or null when the owner has none.
     */
    public function execute(Model $owner, ?string $scope = null): ?SeoProfileData;
}
