<?php

declare(strict_types=1);

namespace Nvl\Seo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Models\SeoProfile;

/**
 * Generates structurally valid SEO profiles for consumer tests.
 *
 * @api
 *
 * @extends Factory<SeoProfile>
 */
final class SeoProfileFactory extends Factory
{
    protected $model = SeoProfile::class;

    /**
     * Prepare native parent and owner facts after Laravel expands relationships.
     *
     * @internal
     */
    public function configure(): static
    {
        $expandRelationships = true;

        return $this->state(function () use (&$expandRelationships): array {
            $expandRelationships = $this->expandRelationships;

            return [];
        })->afterMaking(function (SeoProfile $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            $owner = FactoryGuard::owner($model, 'seoable_type', 'seoable_id');
            FactoryGuard::inherit($model, $owner);
        });
    }

    /**
     * @return array<model-property<SeoProfile>, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => 'default',
            'seoable_type' => null,
            'seoable_id' => null,
            'is_indexable' => true,
            'is_followable' => true,
            'max_image_preview' => 'large',
            'sitemap_included' => true,
            'sitemap_priority' => '0.5',
            'sitemap_change_frequency' => SitemapChangeFrequency::Weekly,
            'metadata' => null,
        ];
    }

    /** Associate a persisted owner using its native morph identity.
     *
     * @api
     */
    public function forOwner(Model $owner): static
    {
        FactoryGuard::parent($owner, new SeoProfile);

        return $this->state([
            'seoable_type' => $owner->getMorphClass(),
            'seoable_id' => (string) FactoryGuard::identifier($owner->getKey()),
        ]);
    }
}
