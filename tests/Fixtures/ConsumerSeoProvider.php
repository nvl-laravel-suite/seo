<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nvl\Ownership\Http\Middleware\ResolvePublicOwnerSite;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Tests\Fixtures\Http\Controllers\RobotsController;
use Nvl\Seo\Tests\Fixtures\Http\Controllers\SitemapChunkController;
use Nvl\Seo\Tests\Fixtures\Http\Controllers\SitemapController;

final class ConsumerSeoProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SeoAuthorization::class, fn () => new class implements SeoAuthorization
        {
            public function authorize(SeoAuthorizationContext $context): void
            {
                $ability = config('nvl-seo.authorization.ability');
                if (is_string($ability) && $ability !== '') {
                    Gate::authorize($ability, [$context]);
                }
            }
        });
    }

    public function boot(): void
    {
        $this->registerRoutes();
        if (config('nvl-seo.management.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/management.php');
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached() || ! (bool) config('nvl-seo.routes.enabled', false)) {
            return;
        }

        $middleware = config('nvl-seo.routes.middleware', ['web']);
        $middleware = is_array($middleware) ? $middleware : ['web'];
        $middleware = array_values(array_filter($middleware, is_string(...)));

        if (SeoOwnership::declared()) {
            array_unshift($middleware, ResolvePublicOwnerSite::class);
        }
        $middleware = array_values(array_unique($middleware));

        Route::middleware($middleware)
            ->name(SeoRouteConfiguration::publicName())
            ->group(function (): void {
                Route::get(
                    SeoRouteConfiguration::sitemapPath(),
                    SitemapController::class,
                )->name('sitemap');
                Route::get(
                    SeoRouteConfiguration::sitemapChunkPath(),
                    SitemapChunkController::class,
                )->whereNumber('chunk')->name('sitemap.chunk');
                Route::get(
                    SeoRouteConfiguration::robotsPath(),
                    RobotsController::class,
                )->name('robots');
            });

    }
}
