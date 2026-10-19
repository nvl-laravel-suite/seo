<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

/**
 * Declares exact canonical resources queried by a freshly resolved public sitemap source.
 *
 * @api
 */
interface OwnershipSafeSitemapSource extends SitemapSource
{
    /** @return list<string> */
    public function ownershipResources(): array;
}
