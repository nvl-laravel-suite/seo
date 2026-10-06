<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Models\SeoProfile;

/**
 * Defines the consumer-facing DuplicateSeoProfileAction workflow.
 *
 * @api
 */
interface DuplicateSeoProfileContract
{
    /**
     * Execute the execute workflow.
     */
    public function execute(
        SeoProfile|string $source,
        Model $target,
        ?string $scope = null,
        bool $copyPaths = false,
    ): SeoProfile;
}
