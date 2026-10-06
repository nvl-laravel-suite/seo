<?php

declare(strict_types=1);

namespace Nvl\Seo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Seo\Models\SeoRedirect;

/**
 * Builds SeoRedirect fixture rows and their declared package parents.
 *
 * @extends Factory<SeoRedirect>
 *
 * @api
 */
final class SeoRedirectFactory extends Factory
{
    protected $model = SeoRedirect::class;

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
        })->afterMaking(function (SeoRedirect $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            FactoryGuard::root($model, 'seo.redirects');
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<SeoRedirect>, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => 'default',
            'source_path' => '/'.$this->faker->unique()->slug(3),
            'target' => '/'.$this->faker->slug(3),
            'status_code' => 301,
            'is_active' => true,
            'revision' => 1,
        ];
    }
}
