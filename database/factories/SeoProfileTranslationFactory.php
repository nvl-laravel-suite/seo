<?php

declare(strict_types=1);

namespace Nvl\Seo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;

/**
 * Builds SeoProfileTranslation fixture rows and their declared package parents.
 *
 * @extends Factory<SeoProfileTranslation>
 *
 * @api
 */
final class SeoProfileTranslationFactory extends Factory
{
    protected $model = SeoProfileTranslation::class;

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
        })->afterMaking(function (SeoProfileTranslation $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            if ($model->getAttribute('seo_profile_id') !== null) {
                $parent = SeoProfile::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('seo_profile_id')));
                FactoryGuard::parent($parent, $model);
                FactoryGuard::inherit($model, $parent);
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<SeoProfileTranslation>, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_profile_id' => SeoProfile::factory(),
            'locale' => 'en',
            'title' => $this->faker->sentence(3),
        ];
    }

    /**
     * Associate an admitted persisted SeoProfile parent.
     *
     * @api
     */
    public function forProfile(SeoProfile $parent): static
    {
        FactoryGuard::parent($parent, new SeoProfileTranslation);

        return $this->state([
            'seo_profile_id' => $parent->getKey(),
        ]);
    }
}
