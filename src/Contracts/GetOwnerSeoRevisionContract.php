<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Data\SeoOwnerRevisionData;

/**
 * Defines the consumer-facing GetOwnerSeoRevisionAction workflow.
 *
 * @api
 */
interface GetOwnerSeoRevisionContract
{
    /**
     * Return revision zero with no profile identifier when no profile exists.
     */
    public function execute(Model $owner, ?string $scope = null): SeoOwnerRevisionData;
}
