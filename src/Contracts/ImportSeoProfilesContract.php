<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\Import\SeoImportResultData;

/**
 * Defines the consumer-facing ImportSeoProfilesAction workflow.
 *
 * @api
 */
interface ImportSeoProfilesContract
{
    /**
     * Import one bounded page and return its continuation contract.
     */
    public function execute(
        SeoImportSource $source,
        ?string $cursor = null,
        int $limit = 100,
    ): SeoImportResultData;
}
