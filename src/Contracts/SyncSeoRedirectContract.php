<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\Mutations\SeoRedirectPayload;
use Nvl\Seo\Models\SeoRedirect;

/**
 * Defines the consumer-facing SyncSeoRedirectAction workflow.
 *
 * @api
 */
interface SyncSeoRedirectContract
{
    /**
     * Persist one normalized redirect while enforcing optimistic concurrency.
     */
    public function execute(
        SeoRedirect|string|null $redirect,
        SeoRedirectPayload $data,
        ?string $scope = null,
    ): SeoRedirect;
}
