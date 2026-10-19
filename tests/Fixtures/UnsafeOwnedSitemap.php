<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Nvl\Seo\Contracts\SitemapSource;
use Nvl\Seo\Data\SitemapEntry;

/** Deliberately lacks the ownership-safe source contract. */
final class UnsafeOwnedSitemap implements SitemapSource
{
    public function entries(string $scope): iterable
    {
        yield new SitemapEntry('https://b.seo.test/private');
    }
}
