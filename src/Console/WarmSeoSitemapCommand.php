<?php

declare(strict_types=1);

namespace Nvl\Seo\Console;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Seo\Support\SeoConfiguration;
use Nvl\Seo\Support\SeoScope;

/**
 * Builds and publishes sitemap artifacts for one scope.
 */
final class WarmSeoSitemapCommand extends Command
{
    protected $signature = 'nvl:seo:sitemap:warm
        {--scope= : Site scope to build}
        {--origin= : Verified public owner site origin}';

    protected $description = 'Build and publish SEO sitemap artifacts for one scope';

    /**
     * Build the requested sitemap and report its published shape.
     */
    public function handle(SitemapGenerator $sitemaps, Container $container): int
    {
        $origin = $this->option('origin');
        if (is_string($origin) && $origin !== '') {
            return $container->make(PublicOwnerSiteRunner::class)->run(
                Request::create($origin), fn (): int => $this->warm($sitemaps),
            );
        }
        if (SeoOwnership::declared()) {
            $this->components->error('Owned sitemap warming requires --origin and a verified public site resolver.');

            return self::INVALID;
        }

        return $this->warm($sitemaps);
    }

    /** Publish under the already admitted site and owner resource graph. */
    private function warm(SitemapGenerator $sitemaps): int
    {
        if (SeoConfiguration::nonNegativeInteger('nvl-seo.sitemap.cache_seconds', 3600) === 0) {
            $this->components->error(
                'Sitemap warming requires a positive seo.sitemap.cache_seconds value.',
            );

            return self::INVALID;
        }

        $scope = $this->option('scope');

        try {
            $scope = SeoScope::normalize(is_string($scope) && $scope !== '' ? $scope : null);
        } catch (InvalidSeoMutationException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $xml = $sitemaps->generate($scope);
        $chunks = $sitemaps->chunkCount($scope);
        $this->components->info(
            "Published {$chunks} sitemap chunk(s) for scope [{$scope}] "
            .'with '.strlen($xml).' bytes in the primary document.',
        );

        return self::SUCCESS;
    }
}
