<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Seo\Data\SeoProfileData;
use Nvl\Seo\Data\SeoProfileQuery;

/**
 * Defines the consumer-facing ListSeoProfilesAction workflow.
 *
 * @api
 */
interface ListSeoProfilesContract
{
    /**
     * Return one authorized, bounded page of management profiles.
     *
     * @return LengthAwarePaginator<int, SeoProfileData>
     */
    public function execute(SeoProfileQuery $query): LengthAwarePaginator;
}
