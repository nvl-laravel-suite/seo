<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nvl\Data\Providers\DataServiceProvider;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Tests\Fixtures\TestIntegerSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestStringSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestStructuredDataProvider;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Boots SEO and its explicit runtime dependencies in isolation.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocaleServiceProvider::class,
            DataServiceProvider::class,
            TranslatableServiceProvider::class,
            SeoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'app.url' => 'https://example.test',
            'app.locale' => 'en',
            'app.fallback_locale' => 'en',
            'nvl-translatable.locales' => ['en', 'bg'],
            'nvl-translatable.fallback_locales' => ['en'],
            'nvl-seo.site.base_url' => 'https://example.test',
            'nvl-seo.site.name' => 'Example',
            'nvl-seo.owners' => [
                'article' => TestSeoOwner::class,
                'integer-article' => TestIntegerSeoOwner::class,
                'string-article' => TestStringSeoOwner::class,
            ],
            'nvl-seo.routes.enabled' => true,
            'nvl-seo.routes.sitemap_path' => 'sitemap.xml',
            'nvl-seo.routes.sitemap_chunk_path' => 'sitemap-{chunk}.xml',
            'nvl-seo.routes.robots_path' => 'robots.txt',
            'nvl-seo.routes.middleware' => [],
            'nvl-seo.sitemap.cache_seconds' => 3600,
            'nvl-seo.structured_data.providers' => [
                [
                    'key' => 'test.configured-resource',
                    'resource' => TestSeoOwner::class,
                    'provider' => TestStructuredDataProvider::class,
                    'priority' => 10,
                ],
            ],
        ]);
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('seo_test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('seo_test_integer_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $driver = Schema::getConnection()->getDriverName();
        Schema::create('seo_test_string_owners', function (Blueprint $table) use ($driver): void {
            $table->string('id')->collation(match ($driver) {
                'mysql', 'mariadb' => 'utf8mb4_bin',
                'pgsql' => 'C',
                default => 'BINARY',
            })->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
