<?php

declare(strict_types=1);

namespace Nvl\Seo\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Seo\Console\ClearSeoSitemapCommand;
use Nvl\Seo\Console\PruneSeoRedirectsCommand;
use Nvl\Seo\Console\SeoDoctorCommand;
use Nvl\Seo\Console\WarmSeoSitemapCommand;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Contracts\SeoImageResolver;
use Nvl\Seo\Contracts\SitemapArtifactStore;
use Nvl\Seo\Contracts\StructuredDataProvider;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Ownership\SeoAdoptionAdapter;
use Nvl\Seo\Ownership\SeoResourceRegistrar as OwnershipSeoResourceRegistrar;
use Nvl\Seo\Services\ConfiguredSeoAuthorization;
use Nvl\Seo\Services\DirectSeoImageResolver;
use Nvl\Seo\Services\EloquentSeoSitemapSource;
use Nvl\Seo\Services\FilesystemSitemapArtifactStore;
use Nvl\Seo\Services\SeoDoctor;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Seo\Services\StructuredDataRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Ownership\Contracts\OwnershipIntegratedProvider;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/**
 * Registers the localized SEO runtime and its optional public routes.
 */
final class SeoServiceProvider extends ServiceProvider implements OwnershipIntegratedProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    /**
     * Register configuration and package boundary contracts.
     */
    public function register(): void
    {

        $this->app->register(SupportServiceProvider::class);
        PackageDoctorContributor::register($this->app, 'nvl/seo', fn (): array => $this->app->make(SeoDoctor::class)->inspect());

        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-seo.php', 'seo');
        $imageResolver = config('nvl-seo.image_resolver', DirectSeoImageResolver::class);

        if (! is_string($imageResolver)) {
            throw new InvalidArgumentException(
                'seo.image_resolver must implement SeoImageResolver.',
            );
        }
        if (! is_a($imageResolver, SeoImageResolver::class, true)) {
            throw new InvalidArgumentException('seo.image_resolver must implement SeoImageResolver.');
        }

        $this->app->bindIf(SeoImageResolver::class, $imageResolver);
        $artifactStore = config(
            'nvl-seo.sitemap.artifact_store',
            FilesystemSitemapArtifactStore::class,
        );

        if (! is_string($artifactStore)
            || ! is_a($artifactStore, SitemapArtifactStore::class, true)) {
            throw new InvalidArgumentException(
                'seo.sitemap.artifact_store must implement SitemapArtifactStore.',
            );
        }

        $this->app->bindIf(SitemapArtifactStore::class, $artifactStore);
        $this->app->bindIf(SeoAuthorization::class, ConfiguredSeoAuthorization::class);
        $this->app->singleton(SeoOwnerRegistry::class);
        $this->app->singleton(SitemapRegistry::class);
        $this->app->singleton(StructuredDataRegistry::class);
    }

    /**
     * Load package resources and register integrations.
     */
    public function boot(
        TranslationResourceRegistry $translationResources,
        SitemapRegistry $sitemaps,
        StructuredDataRegistry $structuredData,
    ): void {
        (new OwnershipSeoResourceRegistrar)->register($this->app->make(OwnershipResourceRegistry::class));
        if ($this->app->bound(OwnershipAdoptionRegistry::class)) {
            $this->app->make(OwnershipAdoptionRegistry::class)->register('seo', SeoAdoptionAdapter::class);
        }

        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'nvl-seo-skills');
        $this->app->make(GlobalNames::class)->translations('seo', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-seo'),
        ], 'nvl-seo-translations');

        if ((bool) config('nvl-seo.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $translationResources->register(
            key: 'seo.profiles',
            modelClass: SeoProfile::class,
            label: 'SEO profiles',
            searchableColumns: ['seoable_type', 'seoable_id', 'scope'],
            displayColumns: ['seoable_type', 'seoable_id', 'scope', 'is_indexable'],
            orderColumn: 'updated_at',
        );
        $sitemaps->registerType(EloquentSeoSitemapSource::class, 'nvl/seo');
        $this->registerConfiguredSitemapSources($sitemaps);
        $this->registerConfiguredStructuredDataProviders($structuredData);
        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearSeoSitemapCommand::class,
                PruneSeoRedirectsCommand::class,
                SeoDoctorCommand::class,
                WarmSeoSitemapCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../../config/nvl-seo.php' => config_path('nvl-seo.php'),
        ], 'seo-config');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'seo-migrations');

    }

    /**
     * Resolve configured sitemap source classes through the container.
     */
    private function registerConfiguredSitemapSources(SitemapRegistry $sitemaps): void
    {
        $sources = config('nvl-seo.sitemap.sources', []);

        if (! is_array($sources)) {
            throw new InvalidArgumentException(
                'seo.sitemap.sources must be an array of SitemapSource classes.',
            );
        }

        foreach ($sources as $index => $source) {
            if (! is_string($source)) {
                throw new InvalidArgumentException(
                    "Configured sitemap source [{$index}] must implement SitemapSource.",
                );
            }

            $sitemaps->registerType($source);
        }
    }

    /**
     * Resolve configured resource-aware structured-data providers.
     */
    private function registerConfiguredStructuredDataProviders(
        StructuredDataRegistry $structuredData,
    ): void {
        $providers = config('nvl-seo.structured_data.providers', []);

        if (! is_array($providers)) {
            throw new InvalidArgumentException(
                'seo.structured_data.providers must be an array.',
            );
        }

        foreach ($providers as $index => $configuration) {
            if (! is_array($configuration)) {
                throw new InvalidArgumentException(
                    "Structured-data provider configuration [{$index}] must be an array.",
                );
            }

            $resourceClass = $configuration['resource'] ?? null;
            $providerClass = $configuration['provider'] ?? null;
            $key = $configuration['key'] ?? $providerClass;
            $priority = $configuration['priority'] ?? 0;

            if (! is_string($resourceClass)
                || ! is_a($resourceClass, Model::class, true)
                || ! is_string($providerClass)
                || ! is_a($providerClass, StructuredDataProvider::class, true)
                || ! is_string($key)
                || ! is_int($priority)) {
                throw new InvalidArgumentException(
                    "Structured-data provider configuration [{$index}] is invalid.",
                );
            }

            $provider = $this->app->make($providerClass);

            if (! $provider instanceof StructuredDataProvider) {
                throw new InvalidArgumentException(
                    "Structured-data provider [{$providerClass}] could not be resolved.",
                );
            }

            /** @var class-string<Model> $resourceClass */
            $structuredData->register($key, $resourceClass, $provider, $priority);
        }
    }
}
