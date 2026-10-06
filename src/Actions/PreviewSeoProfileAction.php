<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Nvl\Seo\Contracts\PreviewSeoProfileContract;
use Nvl\Seo\Data\ResolvedSeoData;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoMetadataResolver;

/**
 * Resolves the exact runtime metadata shown to a crawler or social client.
 *
 * @api
 */
final readonly class PreviewSeoProfileAction implements PreviewSeoProfileContract
{
    public function __construct(private SeoMetadataResolver $resolver) {}

    public function execute(SeoProfile $profile, ?string $locale = null): ResolvedSeoData
    {
        return $this->resolver->resolve($profile, $locale);
    }
}
