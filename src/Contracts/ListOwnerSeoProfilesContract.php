<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Data\SeoProfileData;

/**
 * Defines the consumer-facing ListOwnerSeoProfilesAction workflow.
 *
 * @api
 */
interface ListOwnerSeoProfilesContract
{
    /**
     * Return one profile or null for each owner in the same positional order.
     *
     * @param  iterable<array-key, Model>  $owners
     * @return list<SeoProfileData|null>
     */
    public function execute(iterable $owners, ?string $scope = null): array;
}
