<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Support\SeoImageContext;
use Nvl\Seo\ValueObjects\SeoImage;

/**
 * Resolves social image URLs from direct URLs or application media references.
 *
 * @api
 */
interface SeoImageResolver
{
    public function resolve(SeoImageContext $context): ?SeoImage;
}
