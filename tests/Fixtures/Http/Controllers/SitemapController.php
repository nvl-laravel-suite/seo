<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Seo\Support\SeoConfiguration;
use Nvl\Seo\Tests\Fixtures\SeoManagementInput;

/**
 * Serves the generated XML sitemap when public routes are enabled.
 */
final readonly class SitemapController
{
    public function __construct(
        private SitemapGenerator $sitemaps,
    ) {}

    /**
     * Serve the sitemap with validation and conditional caching headers.
     */
    public function __invoke(Request $request): Response
    {
        $query = SeoManagementInput::sitemap($request->query());
        $xml = $this->sitemaps->generate($query['scope'] ?? null);
        $response = response(
            $xml,
            200,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
        $response->setEtag(hash('sha256', $xml));
        $seconds = SeoConfiguration::nonNegativeInteger('nvl-seo.sitemap.cache_seconds', 3600);

        if ($seconds > 0) {
            $response->setPublic();
            $response->setMaxAge($seconds);
        } else {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        $response->isNotModified($request);

        return $response;
    }
}
