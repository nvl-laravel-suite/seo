<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Contracts\SeoImageResolver;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Enums\TwitterCard;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\ExactSeoOwnerText;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoConfiguration;
use Nvl\Seo\Support\SeoImageContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Translatable\Services\ContentLocale;

/**
 * Resolves persisted SEO, deterministic locale fallbacks, and site defaults.
 *
 * @api
 */
final readonly class SeoReader
{
    public function __construct(
        private SeoAuthorization $authorization,
        private SeoOwnerRegistry $owners,
        private ContentLocale $contentLocale,
        private SeoImageResolver $images,
        private AbsoluteUrl $urls,
        private StructuredDataResolver $structuredData,
        private Repository $configuration,
        private OwnershipBoundary $tenancy,
        private LocaleCatalog $locales,
        private OwnershipResourceRegistry $ownershipResources,
    ) {}

    /** @return array{locale:string,title:string|null,description:string|null,canonicalUrl:string|null,robots:string,alternates:array<string,string>,openGraph:array<string,string>,openGraphLocales:list<string>,twitter:array<string,string>,structuredData:list<array<string,mixed>>} */
    public function resolve(
        Authenticatable|SystemActor|PublicActor $actor,
        Model|SeoProfile $owner,
        ?string $locale = null,
        ?string $scope = null,
    ): array {
        $owner = $this->canonicalOwner($owner);
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Resolve, owner: $owner, scope: $scope));
        $locale ??= $this->contentLocale->get();
        $profile = $owner instanceof SeoProfile
            ? $owner
            : SeoModelRegistry::profiles()
                ->forOwner($owner, $scope)
                ->active()
                ->with('translations')
                ->first();

        if (! $profile instanceof SeoProfile || $profile->status !== 'active') {
            return $this->defaults($locale);
        }

        $profile->loadMissing('translations');
        $translation = $profile->getTranslation($locale);

        if (! $translation instanceof SeoProfileTranslation) {
            return $this->defaults($locale, $this->robots($profile));
        }

        $canonical = $this->urls->resolve($translation->canonical_url ?? $translation->path);
        $plainTitle = $this->translatedString($profile, 'title', $locale);
        $description = $this->translatedString($profile, 'description', $locale);
        $plainTitle = $plainTitle === null
            ? $this->stringConfig('nvl-seo.defaults.title')
            : $plainTitle;
        $description = $description === null
            ? $this->stringConfig('nvl-seo.defaults.description')
            : ($description !== '' ? $description : null);
        $imageContext = new SeoImageContext(
            profile: $profile,
            translation: $translation,
            locale: $locale,
            url: $this->translatedString($profile, 'image_url', $locale),
            reference: $this->translatedString($profile, 'image_reference', $locale),
            alt: $this->translatedString($profile, 'image_alt', $locale),
        );
        if (SeoOwnership::declared()) {
            if (! $this->images instanceof OwnershipSafeSeoImageResolver) {
                throw new \LogicException('The SEO image resolver has no ownership capability.');
            }
            $this->images->assertOwnershipSafe($imageContext);
        }
        $image = $this->images->resolve($imageContext);
        $alternates = [];
        $translations = $profile->translations->sortBy('locale', SORT_STRING);

        foreach ($translations as $alternate) {
            $url = $this->urls->resolve($alternate->canonical_url ?? $alternate->path);

            if ($url !== null) {
                $alternates[$alternate->locale] = $url;
            }
        }

        $fallbackLocale = $this->locales->fallbacks()[0] ?? null;
        if (is_string($fallbackLocale) && isset($alternates[$fallbackLocale])) {
            $alternates['x-default'] = $alternates[$fallbackLocale];
        }
        $openGraphLocales = [];

        foreach (array_keys($alternates) as $alternateLocale) {
            if ($alternateLocale !== 'x-default' && $alternateLocale !== $translation->locale) {
                $openGraphLocales[] = str_replace('-', '_', $alternateLocale);
            }
        }

        $openGraphTitle = $this->translatedString($profile, 'open_graph_title', $locale);
        $openGraphDescription = $this->translatedString(
            $profile,
            'open_graph_description',
            $locale,
        );
        $openGraph = array_filter([
            'og:type' => SeoConfiguration::string('nvl-seo.site.open_graph_type', 'website'),
            'og:locale' => str_replace('-', '_', $translation->locale),
            'og:site_name' => SeoConfiguration::string('nvl-seo.site.name', ''),
            'og:title' => $openGraphTitle ?? $plainTitle,
            'og:description' => $openGraphDescription ?? $description,
            'og:url' => $canonical,
            'og:image' => $image?->url,
            'og:image:alt' => $image?->alt,
            'og:image:width' => $image?->width !== null ? (string) $image->width : null,
            'og:image:height' => $image?->height !== null ? (string) $image->height : null,
            'og:image:type' => $image?->mimeType,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
        $twitterCard = $profile->translated('twitter_card', $locale);
        $twitter = array_filter([
            'twitter:card' => $twitterCard instanceof TwitterCard
                ? $twitterCard->value
                : (is_string($twitterCard) && $twitterCard !== ''
                    ? $twitterCard
                    : SeoConfiguration::string(
                        'nvl-seo.defaults.twitter_card',
                        'summary_large_image',
                    )),
            'twitter:site' => $this->stringConfig('nvl-seo.site.twitter_site'),
            'twitter:creator' => $this->stringConfig('nvl-seo.site.twitter_creator'),
            'twitter:title' => $this->translatedString($profile, 'twitter_title', $locale)
                ?? $openGraphTitle
                ?? $plainTitle,
            'twitter:description' => $this->translatedString(
                $profile,
                'twitter_description',
                $locale,
            )
                ?? $openGraphDescription
                ?? $description,
            'twitter:image' => $image?->url,
            'twitter:image:alt' => $image?->alt,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
        $resource = $owner instanceof SeoProfile
            ? $profile->seoable()->first()
            : $owner;
        $resource = $resource instanceof Model ? $resource : $profile;
        $siteUrl = $this->urls->resolve('/') ?? SeoConfiguration::string(
            'nvl-seo.site.base_url',
            'http://localhost',
        );

        return [
            'locale' => $translation->locale,
            'title' => $this->brandedTitle($plainTitle),
            'description' => $description,
            'canonicalUrl' => $canonical,
            'robots' => $this->robots($profile),
            'alternates' => $alternates,
            'openGraph' => $openGraph,
            'openGraphLocales' => $openGraphLocales,
            'twitter' => $twitter,
            'structuredData' => $this->structuredData->resolve(
                resource: $resource,
                context: [
                    'resourceType' => $resource->getMorphClass(),
                    'resourceId' => SeoModelIdentifier::required($resource),
                    'profileId' => $profile->id,
                    'locale' => $translation->locale,
                    'scope' => $profile->scope,
                    'canonicalUrl' => $canonical,
                    'title' => $plainTitle,
                    'description' => $description,
                    'imageUrl' => $image?->url,
                    'siteName' => SeoConfiguration::string('nvl-seo.site.name', ''),
                    'siteUrl' => rtrim($siteUrl, '/'),
                ],
                persisted: $this->translatedArray($profile, 'structured_data', $locale),
            ),
        ];
    }

    /** Canonically reload supplied models before any metadata, image, or provider work. */
    private function canonicalOwner(Model|SeoProfile $owner): Model|SeoProfile
    {
        $prototype = $owner instanceof SeoProfile ? SeoModelRegistry::newProfile() : new ($owner::class);
        $key = $prototype->getKeyName();
        if (($owner instanceof SeoProfile && $owner::class !== SeoModelRegistry::profileClass())
            || ! $owner->exists || $owner->getKey() === null || $owner->isDirty($key)
            || $owner->getRawOriginal($key) !== $owner->getKey()
            || $owner->getKeyName() !== $key || $owner->getTable() !== $prototype->getTable()
            || $owner->getConnection() !== $prototype->getConnection()) {
            throw InvalidSeoMutationException::because('SEO resolution requires an unchanged canonical persisted identity.');
        }
        if (! $owner instanceof SeoProfile) {
            return $this->owners->resolve($this->owners->aliasFor($owner), SeoModelIdentifier::required($owner));
        }
        $canonical = SeoModelRegistry::profiles()->useWritePdo()->findOrFail(SeoModelIdentifier::required($owner));
        if (SeoOwnership::active()) {
            $resource = $this->ownershipResources->forModel($canonical);
            $this->tenancy->assertRecord($canonical, $resource->key);
        }

        return $canonical;
    }

    /** @return array{locale:string,title:string|null,description:string|null,canonicalUrl:string|null,robots:string,alternates:array<string,string>,openGraph:array<string,string>,openGraphLocales:list<string>,twitter:array<string,string>,structuredData:list<array<string,mixed>>} */
    private function defaults(string $locale, ?string $robots = null): array
    {
        return [
            'locale' => $locale,
            'title' => $this->brandedTitle($this->stringConfig('nvl-seo.defaults.title')),
            'description' => $this->stringConfig('nvl-seo.defaults.description'),
            'canonicalUrl' => null,
            'robots' => $robots ?? $this->defaultRobots(),
            'alternates' => [],
            'openGraph' => [],
            'openGraphLocales' => [],
            'twitter' => [],
            'structuredData' => [],
        ];
    }

    private function brandedTitle(?string $title): ?string
    {
        if ($title === '') {
            return null;
        }

        $siteName = $this->stringConfig('nvl-seo.site.name');

        if ($title === null) {
            return $siteName;
        }

        if ($siteName === null || $siteName === '' || $siteName === $title) {
            return $title;
        }

        $separator = SeoConfiguration::string('nvl-seo.site.title_separator', ' | ');

        return config('nvl-seo.site.title_position', 'suffix') === 'prefix'
            ? $siteName.$separator.$title
            : $title.$separator.$siteName;
    }

    private function robots(SeoProfile $profile): string
    {
        $directives = [
            $profile->is_indexable ? 'index' : 'noindex',
            $profile->is_followable ? 'follow' : 'nofollow',
        ];

        if ($profile->max_snippet !== null) {
            $directives[] = 'max-snippet:'.$profile->max_snippet;
        }

        if ($profile->max_image_preview !== null) {
            $directives[] = 'max-image-preview:'.$profile->max_image_preview;
        }

        if ($profile->max_video_preview !== null) {
            $directives[] = 'max-video-preview:'.$profile->max_video_preview;
        }

        return implode(', ', $directives);
    }

    private function defaultRobots(): string
    {
        $index = (bool) config('nvl-seo.defaults.robots.index', true);
        $follow = (bool) config('nvl-seo.defaults.robots.follow', true);
        $directives = [$index ? 'index' : 'noindex', $follow ? 'follow' : 'nofollow'];
        $maxImage = $this->stringConfig('nvl-seo.defaults.robots.max_image_preview');
        $maxSnippet = config('nvl-seo.defaults.robots.max_snippet');
        $maxVideo = config('nvl-seo.defaults.robots.max_video_preview');

        if (is_int($maxSnippet)) {
            $directives[] = 'max-snippet:'.$maxSnippet;
        }

        if ($maxImage !== null) {
            $directives[] = 'max-image-preview:'.$maxImage;
        }

        if (is_int($maxVideo)) {
            $directives[] = 'max-video-preview:'.$maxVideo;
        }

        return implode(', ', $directives);
    }

    private function stringConfig(string $key): ?string
    {
        $value = $this->configuration->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function translatedString(SeoProfile $profile, string $field, string $locale): ?string
    {
        $value = $profile->translated($field, $locale);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function translatedArray(SeoProfile $profile, string $field, string $locale): ?array
    {
        $value = $profile->translated($field, $locale);

        return is_array($value) ? $value : null;
    }

    public function get(Authenticatable|SystemActor $actor, SeoProfile|string $profile): SeoProfile
    {
        $profileId = $profile instanceof SeoProfile ? $profile->id : SeoModelIdentifier::uuid($profile, SeoProfile::class);
        $profile = SeoModelRegistry::profiles()
            ->with('translations')
            ->findOrFail($profileId);
        $owner = $profile->seoable()->firstOrFail();
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor,
            ability: SeoAbility::View,
            profile: $profile,
            owner: $owner,
            ownerAlias: $this->owners->aliasFor($owner),
            scope: $profile->scope,
        ));

        return $profile;
    }

    public function forOwner(Authenticatable|SystemActor $actor, Model $owner, ?string $scope = null): ?SeoProfile
    {
        $scope = SeoScope::normalize($scope);
        $owner = $this->owners->admitForRead([$owner], $scope, $this->authorization, $actor)[0]['owner'];
        $profile = SeoModelRegistry::profiles()
            ->with('translations')
            ->forOwner($owner, $scope)
            ->first();

        return $profile instanceof SeoProfile
            ? $profile
            : null;
    }

    /**
     * @param  iterable<array-key,Model>  $owners
     * @return list<SeoProfile|null>
     */
    public function forOwners(Authenticatable|SystemActor $actor, iterable $owners, ?string $scope = null): array
    {
        $scope = SeoScope::normalize($scope);
        $normalized = $this->owners->admitForRead($owners, $scope, $this->authorization, $actor);

        if ($normalized === []) {
            return [];
        }

        /** @var array<string, array<string, true>> $identifiersByType */
        $identifiersByType = [];

        foreach ($normalized as $identity) {
            $identifiersByType[$identity['type']][$identity['id']] = true;
        }

        $profiles = SeoModelRegistry::profiles()
            ->with('translations')
            ->where('scope', $scope)
            ->where(static function (Builder $query) use ($identifiersByType): void {
                foreach ($identifiersByType as $type => $identifiers) {
                    $query->orWhere(static function (Builder $query) use (
                        $type,
                        $identifiers,
                    ): void {
                        $query
                            ->where('seoable_type', $type)
                            ->whereIn('seoable_id', array_keys($identifiers));
                    });
                }
            })
            ->get();
        /** @var array<string, array<string, SeoProfile>> $profilesByType */
        $profilesByType = [];

        foreach ($profiles as $profile) {
            $profilesByType[$profile->seoable_type][$profile->seoable_id] = $profile;
        }

        return array_map(
            fn (array $identity): ?SeoProfile => isset(
                $profilesByType[$identity['type']][$identity['id']],
            )
                ? $profilesByType[$identity['type']][$identity['id']]
                : null,
            $normalized,
        );
    }

    /** @return array{ownerAlias:string,ownerId:string,scope:string,profileId:string|null,revision:int} */
    public function revisions(Authenticatable|SystemActor $actor, Model $owner, ?string $scope = null): array
    {
        $scope = SeoScope::normalize($scope);
        $identity = $this->owners->admitForRead([$owner], $scope, $this->authorization, $actor)[0];
        $owner = $identity['owner'];
        $profile = SeoModelRegistry::profiles()
            ->forOwner($owner, $scope)
            ->first(['id', 'revision']);

        return [
            'ownerAlias' => $identity['alias'],
            'ownerId' => $identity['id'],
            'scope' => $scope,
            'profileId' => $profile instanceof SeoProfile ? $profile->id : null,
            'revision' => $profile instanceof SeoProfile ? $profile->revision : 0,
        ];
    }

    /**
     * @param  Builder<SeoProfile>  $query
     * @return Builder<SeoProfile>
     */
    private function exactOwnerText(Builder $query, string $column, string $value): Builder
    {
        $connection = $query->getModel()->getConnection();

        return $query->whereRaw(new ExactSeoOwnerText($column, $connection->getDriverName()), [$value, $value]);
    }

    /**
     * @param  array{scope?:string|null,ownerAlias?:string|null,ownerId?:int|string|null,status?:string|null,per_page?:int,page?:int}  $query
     * @return LengthAwarePaginator<int,SeoProfile>
     */
    public function paginate(Authenticatable|SystemActor $actor, array $query = []): LengthAwarePaginator
    {
        $unknown = array_diff(array_keys($query), ['scope', 'ownerAlias', 'ownerId', 'status', 'per_page', 'page']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown SEO catalog criterion.');
        }
        $query += ['scope' => null, 'ownerAlias' => null, 'ownerId' => null, 'status' => null, 'per_page' => 50, 'page' => 1];
        $scope = $query['scope'] === null ? null : SeoScope::normalize($query['scope']);
        if ($query['ownerId'] !== null && $query['ownerAlias'] === null) {
            throw new InvalidArgumentException('An SEO owner identity filter requires its registered owner alias.');
        }
        $ownerId = $query['ownerId'] === null ? null : SeoModelIdentifier::normalize($query['ownerId']);
        $owner = $ownerId === null
            ? null : $this->owners->resolveForCleanup($query['ownerAlias'], $ownerId);
        $ownerId = $owner === null ? null : SeoModelIdentifier::required($owner);
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor,
            ability: SeoAbility::List,
            owner: $owner,
            ownerAlias: $query['ownerAlias'],
            scope: $scope,
        ));
        $ownerMorphType = $query['ownerAlias'] === null
            ? null
            : $this->owners->morphTypeForAlias($query['ownerAlias']);

        $profiles = SeoModelRegistry::profiles()
            ->with('translations')
            ->when(
                $scope !== null,
                static fn ($builder) => $builder->where('scope', $scope),
            )
            ->when(
                $query['status'] !== null,
                static fn ($builder) => $builder->where('status', $query['status']),
            );
        if ($ownerMorphType !== null) {
            $this->exactOwnerText($profiles, 'seoable_type', $ownerMorphType);
        }
        if ($ownerId !== null) {
            $this->exactOwnerText($profiles, 'seoable_id', $ownerId);
        }
        $page = $profiles->orderByDesc('updated_at')
            ->orderBy('id')
            ->paginate(
                perPage: min(200, max(1, $query['per_page'])),
                page: max(1, $query['page']),
            );

        return $page;
    }
}
