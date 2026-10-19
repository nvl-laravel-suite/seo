<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver;
use Nvl\Seo\Contracts\TenantSafeSeoImageResolver;
use Nvl\Seo\Data\SeoImage;
use Nvl\Seo\Support\SeoImageContext;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Tenancy\Exceptions\TenantBoundaryViolation;

/**
 * Resolves translation URLs and falls back to the configured site image.
 */
final readonly class DirectSeoImageResolver implements OwnershipSafeSeoImageResolver, TenantSafeSeoImageResolver
{
    public function __construct(
        private AbsoluteUrl $urls,
    ) {}

    public function resolve(SeoImageContext $context): ?SeoImage
    {
        if ($context->url === '') {
            return null;
        }

        $url = $this->urls->resolve(
            $context->url
                ?? (is_string(config('nvl-seo.site.default_image_url'))
                    ? config('nvl-seo.site.default_image_url')
                    : null),
        );

        return $url !== null
            ? new SeoImage($url, $context->alt)
            : null;
    }

    /** Reject opaque references because the URL adapter cannot authorize native Media. */
    public function assertOwnershipSafe(SeoImageContext $context): void
    {
        if (is_string($context->reference) && $context->reference !== '') {
            throw new OwnershipViolation('A Media reference requires an ownership-aware image resolver.');
        }
    }

    /** Direct URLs are safe, but this resolver cannot authorize model-backed references. */
    public function assertTenantSafe(SeoImageContext $context): void
    {
        if (is_string($context->reference) && $context->reference !== '') {
            throw new TenantBoundaryViolation(
                'The direct SEO image resolver cannot authorize a tenant Media reference.',
            );
        }
    }
}
