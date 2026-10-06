<?php

declare(strict_types=1);

namespace Nvl\Seo\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Relations\StringMorphMany;
use Nvl\Seo\Support\SeoScope;

/**
 * Adds scoped SEO profile access to an Eloquent owner.
 *
 * @mixin Model
 *
 * @api
 *
 * @nvl-consumer-relation seoProfiles
 */
trait HasSeo
{
    /**
     * Return every site-scoped SEO profile attached to this model.
     *
     * @return MorphMany<SeoProfile, $this>
     *
     * @internal
     */
    public function seoProfiles(): MorphMany
    {
        $related = new SeoProfile;

        return new StringMorphMany(
            $related->newQuery(),
            $this,
            $related->qualifyColumn('seoable_type'),
            $related->qualifyColumn('seoable_id'),
            $this->getKeyName(),
        );
    }

    /**
     * Resolve the profile for one site scope.
     *
     * @internal
     */
    public function seoProfile(?string $scope = null): ?SeoProfile
    {
        return $this->seoProfiles()
            ->where('scope', SeoScope::normalize($scope))
            ->first();
    }
}
