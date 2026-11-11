<?php

declare(strict_types=1);

namespace Nvl\Seo\Ownership;

use InvalidArgumentException;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Services\SeoModelRegistry;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;
use Nvl\Support\Ownership\ValueObjects\OwnershipResourceDefinition;

/**
 * Compiles exact host-inherited SEO profiles and captured redirect ownership.
 *
 * @internal
 */
final class SeoResourceRegistrar
{
    public function register(OwnershipResourceRegistry $resources): void
    {
        $parents = config('nvl-seo.ownership.owner_resources', []);
        if (! is_array($parents) || ! array_is_list($parents)) {
            throw new InvalidArgumentException('SEO ownership hosts require resource keys.');
        }
        foreach ($parents as $parent) {
            if (! is_string($parent) || $parent === '') {
                throw new InvalidArgumentException('Invalid SEO ownership host.');
            }
        }
        $owned = $parents !== [];
        SeoOwnership::assertCompatibleHosts($owned);
        $models = app(SeoModelRegistry::class)->prepare();
        $resources->register(new OwnershipResourceDefinition('seo.profiles', 'seo', $models['profile'], $owned ? OwnershipDefinition::inherited('seoable') : OwnershipDefinition::unscoped(), parentResources: $parents, immutableFields: $owned ? ['scope'] : []));
        $resources->register(new OwnershipResourceDefinition('seo.translations', 'seo', SeoProfileTranslation::class, $owned ? OwnershipDefinition::inherited('profile') : OwnershipDefinition::unscoped(), parentResource: $owned ? 'seo.profiles' : null, immutableFields: $owned ? ['scope'] : []));
        $resources->register(new OwnershipResourceDefinition('seo.redirects', 'seo', $models['redirect'], $owned ? OwnershipDefinition::captured() : OwnershipDefinition::unscoped(), immutableFields: $owned ? ['scope'] : []));
    }
}
