<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use LogicException;
use Nvl\Seo\Contracts\OwnershipSafeSitemapSource;
use Nvl\Seo\Contracts\TenantSafeSitemapSource;
use Nvl\Seo\Data\SitemapEntry;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\SeoRouteConfiguration;
use Nvl\Support\Contracts\LocaleCatalog;

/**
 * Streams all indexable profile translations from bounded Eloquent chunks.
 */
final readonly class EloquentSeoSitemapSource implements OwnershipSafeSitemapSource, TenantSafeSitemapSource
{
    public function __construct(
        private AbsoluteUrl $urls,
        private LocaleCatalog $locales,
        private ?SitemapLocationPolicy $locations = null,
        private ?SitemapRegistry $sources = null,
    ) {}

    /**
     * @return iterable<SitemapEntry>
     */
    public function entries(string $scope): iterable
    {
        $profiles = SeoProfile::query()
            ->select([
                ...(SeoOwnership::declared() ? ['owner_scope_key', 'seoable_type', 'seoable_id'] : []),
                'id',
                'scope',
                'sitemap_priority',
                'sitemap_change_frequency',
            ])
            ->where('scope', $scope)
            ->whereNotIn('seoable_type', $this->sources?->ownedProfileTypes() ?? [])
            ->active()
            ->where('is_indexable', true)
            ->where('sitemap_included', true)
            ->with([
                'translations',
            ])
            ->lazyById(500, column: 'id');

        foreach ($profiles as $profile) {
            foreach ($this->entriesForProfile($profile) as $entry) {
                yield $entry;
            }
        }
    }

    /** @return list<string> */
    public function ownershipResources(): array
    {
        return ['seo.profiles', 'seo.translations'];
    }

    /** @return list<string> */
    public function tenantResources(): array
    {
        return ['seo.profiles', 'seo.translations'];
    }

    /**
     * Project canonical URLs and alternates for an eligible profile with loaded translations.
     *
     * @return iterable<SitemapEntry>
     */
    public function entriesForProfile(SeoProfile $profile): iterable
    {
        $sitemapUrl = $this->urls->resolve(SeoRouteConfiguration::sitemapPath());

        if ($sitemapUrl === null) {
            throw new LogicException('The configured sitemap URL cannot be resolved.');
        }

        $locations = $this->locationPolicy();
        $alternates = [];
        $entryUrls = [];
        $translations = $profile->translations->sortBy('locale', SORT_STRING);

        foreach ($translations as $translation) {
            $url = $this->urls->resolve($translation->canonical_url ?? $translation->path);

            if ($url !== null) {
                $alternates[$translation->locale] = $url;

                if ($locations->allows($url, $sitemapUrl)) {
                    $entryUrls[$translation->locale] = $url;
                }
            }
        }

        $fallbackLocale = $this->locales->fallbacks()[0] ?? null;
        if (is_string($fallbackLocale) && isset($alternates[$fallbackLocale])) {
            $alternates['x-default'] = $alternates[$fallbackLocale];
        }

        foreach ($translations as $translation) {
            $url = $entryUrls[$translation->locale] ?? null;

            if ($url === null) {
                continue;
            }

            yield new SitemapEntry(
                url: $url,
                changeFrequency: $profile->sitemap_change_frequency,
                priority: $profile->sitemap_priority,
                alternates: $alternates,
            );
        }
    }

    /**
     * Return the shared sitemap location policy.
     */
    private function locationPolicy(): SitemapLocationPolicy
    {
        return $this->locations ?? new SitemapLocationPolicy;
    }
}
