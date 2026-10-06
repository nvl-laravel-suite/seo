<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\SeoProfileStatusData;

/**
 * Defines the consumer-facing SeoProfileStatusAction workflow.
 *
 * @api
 */
interface SeoProfileStatusContract
{
    /**
     * Return aggregate profile status for one optional normalized scope.
     */
    public function execute(?string $scope = null): SeoProfileStatusData;
}
