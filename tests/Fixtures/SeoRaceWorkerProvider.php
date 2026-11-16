<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Closure;
use Illuminate\Support\ServiceProvider;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Support\SeoAuthorizationContext;

/** Rebuilds the parent test's committed PostgreSQL source, owners and authorization in each SEO race worker. */
final class SeoRaceWorkerProvider extends ServiceProvider
{
    /** @var array<string, mixed> */
    public static array $source = [];

    /** Runs on every authorization decision, so a waiting worker can pause at a known point. */
    public static ?Closure $onAuthorize = null;

    /** Configure the worker before the package providers register their defaults. */
    public function register(): void
    {
        $source = self::$source;
        // The parent's name would make models resolve a different connection than the default.
        unset($source['name']);
        $this->app->make('config')->set([
            'app.key' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=',
            'app.locale' => 'en',
            'app.fallback_locale' => 'en',
            'database.default' => 'seo_race',
            'database.connections.seo_race' => $source,
            'nvl-translatable.locales' => ['en', 'bg'],
            'nvl-translatable.fallback_locales' => ['en'],
            'nvl-seo.site.base_url' => 'https://example.test',
            'nvl-seo.owners' => ['article' => TestSeoOwner::class],
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);
        $this->app->instance(SeoAuthorization::class, new class implements SeoAuthorization
        {
            public function authorize(SeoAuthorizationContext $context): void
            {
                if (SeoRaceWorkerProvider::$onAuthorize !== null) {
                    (SeoRaceWorkerProvider::$onAuthorize)($context);
                }
            }
        });
    }
}
