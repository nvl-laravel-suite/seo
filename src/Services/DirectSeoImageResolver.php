<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver;
use Nvl\Seo\Support\SeoImageContext;
use Nvl\Seo\ValueObjects\SeoImage;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

/**
 * Resolves translation URLs and falls back to the configured site image.
 *
 * @internal
 */
final readonly class DirectSeoImageResolver implements OwnershipSafeSeoImageResolver
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
}
