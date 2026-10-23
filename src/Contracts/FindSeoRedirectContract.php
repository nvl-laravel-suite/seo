<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Data\SeoRedirectData;

/**
 * Defines the authorized exact redirect lookup for management and concurrent edits.
 *
 * @api
 */
interface FindSeoRedirectContract
{
    /** Read an exact source, scope and locale without fallback or hit recording. */
    public function execute(string $source, ?string $scope = null, ?string $locale = null): ?SeoRedirectData;
}
