<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Contracts\SeoImportSource;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Events\SeoProfileChanged;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Exceptions\SeoPathConflictException;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Support\DatabaseConstraintViolation;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Translatable\Enums\TranslationSyncMode;
use Nvl\Translatable\Services\RelatedTranslationStore;

/**
 * Complete profile mutations with policy, revisions, translations and cache publication.
 *
 * @api
 *
 * @phpstan-type ProfileMutation array{isIndexable?:bool,isFollowable?:bool,maxSnippet?:int|null,maxImagePreview?:string|null,maxVideoPreview?:int|null,sitemapIncluded?:bool,sitemapPriority?:string|null,sitemapChangeFrequency?:SitemapChangeFrequency|string|null,metadata?:array<string,mixed>|null,translations?:array<string,array<string,mixed>>,expected_revision?:int|null}
 */
final readonly class SeoProfiles
{
    public function __construct(private RelatedTranslationStore $translations, private SeoTranslationNormalizer $translationNormalizer, private SeoPathConflictResolver $pathConflicts, private SeoMutationValidator $validator, private SitemapCache $sitemapCache, private SeoOwnerRegistry $owners, private DomainEventDispatcher $domainEvents, private SeoAuthorization $authorization) {}

    /** @param ProfileMutation $data */
    public function sync(
        Authenticatable|SystemActor $actor,
        Model $owner,
        array $data,
        ?string $scope = null,
        TranslationSyncMode $translationMode = TranslationSyncMode::Patch,
    ): SeoProfile {
        if (! $owner->exists || $owner->getKey() === null) {
            throw new InvalidArgumentException('SEO can only be attached to a persisted model.');
        }

        $owner = $this->owners->resolve(
            $this->owners->aliasFor($owner),
            SeoModelIdentifier::required($owner),
        );

        $this->validator->profile($data);
        $scope = SeoScope::normalize($scope);

        try {
            return SeoModelRegistry::newProfile()->getConnection()->transaction(function () use ($actor, $owner, $data, $scope, $translationMode): SeoProfile {
                $profile = SeoModelRegistry::profiles()
                    ->forOwner($owner, $scope)
                    ->lockForUpdate()
                    ->first();

                if (! $profile instanceof SeoProfile) {
                    if (is_int($data['expected_revision'] ?? null) && $data['expected_revision'] !== 0) {
                        throw StaleSeoProfileException::forProfile('new');
                    }

                    $profile = SeoModelRegistry::newProfile([
                        'scope' => $scope,
                        'seoable_type' => $owner->getMorphClass(),
                        'seoable_id' => SeoModelIdentifier::required($owner),
                    ]);
                } elseif (is_int($data['expected_revision'] ?? null)
                    && $profile->revision !== $data['expected_revision']) {
                    throw StaleSeoProfileException::forProfile($profile->id);
                }

                $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: $profile->exists ? SeoAbility::Update : SeoAbility::Create, profile: $profile, owner: $owner, ownerAlias: $this->owners->aliasFor($owner), scope: $scope));
                $profile->fill(collect($data)->except(['translations', 'expected_revision'])->mapWithKeys(static fn (mixed $value, string $field): array => [Str::snake($field) => $value])->all());

                if ($profile->exists) {
                    $profile->revision++;
                }

                $profile->save();

                if (array_key_exists('translations', $data)) {
                    try {
                        $translations = $this->translationNormalizer->normalize(
                            $data['translations'],
                        );
                    } catch (InvalidArgumentException $exception) {
                        throw InvalidSeoMutationException::forField(
                            'translations',
                            $exception->getMessage(),
                            $exception,
                        );
                    }

                    $this->pathConflicts->assertAvailable(
                        $profile->id,
                        $profile->scope,
                        $translations,
                    );
                    $definition = $profile->translationDefinition();
                    foreach ($translations as $locale => $attributes) {
                        $this->translations->upsert($profile, $definition, $locale, $attributes);
                    }
                    if ($translationMode === TranslationSyncMode::Replace) {
                        $this->translations->deleteExcept($profile, $definition, array_keys($translations));
                    }
                } elseif ($translationMode === TranslationSyncMode::Replace) {
                    $this->translations->deleteExcept($profile, $profile->translationDefinition(), []);
                }

                $profile->refresh()->load('translations');
                $identity = $this->sitemapCache->capture($profile->scope);
                (SeoModelRegistry::newProfile())->getConnection()->afterCommit(function () use ($identity): void {
                    $this->sitemapCache->forgetCaptured($identity);
                });
                $this->domainEvents->dispatch(new SeoProfileChanged($profile->id, $profile->scope, 'synced'), $profile->getConnection());

                return $profile;
            });
        } catch (QueryException $exception) {
            if (DatabaseConstraintViolation::matches($exception, [
                'seo_profiles_i18n_route_unique',
                'seo_i18n_tenant_route_unique',
                'seo_profiles_i18n.scope, seo_profiles_i18n.locale, seo_profiles_i18n.path_hash',
            ])) {
                throw SeoPathConflictException::concurrent($scope, $exception);
            }

            $profileModel = SeoModelRegistry::newProfile();
            $profileTable = $profileModel->getConnection()->getTablePrefix().$profileModel->getTable();
            if (DatabaseConstraintViolation::matches($exception, [
                'seo_profiles_scope_owner_unique',
                'seo_profiles_tenant_scope_owner_unique',
                "{$profileTable}.scope, {$profileTable}.seoable_type, {$profileTable}.seoable_id",
            ])) {
                throw StaleSeoProfileException::forProfile('new', $exception);
            }

            throw $exception;
        }
    }

    /** @return array{active:int,archived:int,total:int} */
    public function status(Authenticatable|SystemActor $actor, ?string $scope = null): array
    {
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::List, scope: $scope));
        $scope = $scope === null ? null : SeoScope::normalize($scope);
        $query = SeoModelRegistry::profiles()->when(
            $scope !== null,
            static fn ($builder) => $builder->where('scope', $scope),
        );
        $active = (clone $query)->where('status', 'active')->count();
        $archived = (clone $query)->where('status', 'archived')->count();

        return [
            'active' => $active,
            'archived' => $archived,
            'total' => $active + $archived,
        ];
    }

    public function duplicate(Authenticatable|SystemActor $actor,
        SeoProfile|string $source,
        Model $target,
        ?string $scope = null,
        bool $copyPaths = false,
    ): SeoProfile {
        $sourceId = $source instanceof SeoProfile ? $source->id : SeoModelIdentifier::uuid($source, SeoProfile::class);
        $source = SeoModelRegistry::profiles()
            ->with('translations')
            ->findOrFail($sourceId);
        $owner = $source->seoable()->firstOrFail();
        $target = $this->owners->resolve($this->owners->aliasFor($target), SeoModelIdentifier::required($target));
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Duplicate, profile: $source, owner: $owner, ownerAlias: $this->owners->aliasFor($owner), targetOwner: $target, targetOwnerAlias: $this->owners->aliasFor($target), scope: $scope));

        /** @var array<string, array<string, mixed>> $translations */
        $translations = $source->translations->sortBy('locale', SORT_STRING)->mapWithKeys(
            static fn (SeoProfileTranslation $translation): array => [
                $translation->locale => [
                    'path' => $copyPaths ? $translation->path : null,
                    'title' => $translation->title,
                    'description' => $translation->description,
                    'canonicalUrl' => $copyPaths ? $translation->canonical_url : null,
                    'imageUrl' => $translation->image_url,
                    'imageReference' => $translation->image_reference,
                    'imageAlt' => $translation->image_alt,
                    'openGraphTitle' => $translation->open_graph_title,
                    'openGraphDescription' => $translation->open_graph_description,
                    'twitterTitle' => $translation->twitter_title,
                    'twitterDescription' => $translation->twitter_description,
                    'twitterCard' => $translation->twitter_card,
                    'structuredData' => $translation->structured_data,
                    'metadata' => $translation->metadata,
                ],
            ],
        )->all();

        return $this->sync($actor,
            $target,
            [
                'isIndexable' => $source->is_indexable,
                'isFollowable' => $source->is_followable,
                'maxSnippet' => $source->max_snippet,
                'maxImagePreview' => $source->max_image_preview,
                'maxVideoPreview' => $source->max_video_preview,
                'sitemapIncluded' => $source->sitemap_included,
                'sitemapPriority' => $source->sitemap_priority,
                'sitemapChangeFrequency' => $source->sitemap_change_frequency,
                'metadata' => $source->metadata,
                'translations' => $translations,
                'expected_revision' => 0,
            ],
            $scope,
            TranslationSyncMode::Replace,
        );
    }

    public function archive(Authenticatable|SystemActor $actor,
        SeoProfile|string $profile,
        bool $archived,
        int $expectedRevision,
    ): SeoProfile {
        $profileId = $profile instanceof SeoProfile ? $profile->id : SeoModelIdentifier::uuid($profile, SeoProfile::class);

        return SeoModelRegistry::newProfile()->getConnection()->transaction(function () use ($actor, $profileId, $archived, $expectedRevision): SeoProfile {
            $profile = SeoModelRegistry::profiles()
                ->lockForUpdate()
                ->findOrFail($profileId);
            $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Archive, profile: $profile, owner: $profile->seoable()->firstOrFail(), scope: $profile->scope));

            if ($profile->revision !== $expectedRevision) {
                throw StaleSeoProfileException::forProfile($profile->id);
            }

            $profile->status = $archived ? 'archived' : 'active';
            $profile->archived_at = $archived ? now() : null;
            $profile->save();
            $profile->refresh()->load('translations');
            $identity = $this->sitemapCache->capture($profile->scope);
            (SeoModelRegistry::newProfile())->getConnection()->afterCommit(function () use ($identity): void {
                $this->sitemapCache->forgetCaptured($identity);
            });
            $this->domainEvents->dispatch(new SeoProfileChanged(
                $profile->id,
                $profile->scope,
                $archived ? 'archived' : 'restored',
            ), $profile->getConnection());

            return $profile;
        });
    }

    public function delete(Authenticatable|SystemActor $actor, SeoProfile|string $profile, ?int $expectedRevision = null): bool
    {
        $profileId = $profile instanceof SeoProfile ? $profile->id : SeoModelIdentifier::uuid($profile, SeoProfile::class);

        return SeoModelRegistry::newProfile()->getConnection()->transaction(function () use ($actor, $profileId, $expectedRevision): bool {
            $profile = SeoModelRegistry::profiles()
                ->lockForUpdate()
                ->findOrFail($profileId);
            $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Delete, profile: $profile, owner: $profile->seoable()->withTrashed()->firstOrFail(), scope: $profile->scope));

            if ($expectedRevision !== null && $profile->revision !== $expectedRevision) {
                throw StaleSeoProfileException::forProfile($profile->id);
            }

            $id = $profile->id;
            $scope = $profile->scope;
            $profile->translations()->delete();
            $deleted = (bool) $profile->delete();

            if ($deleted) {
                $identity = $this->sitemapCache->capture($scope);
                (SeoModelRegistry::newProfile())->getConnection()->afterCommit(function () use ($identity): void {
                    $this->sitemapCache->forgetCaptured($identity);
                });
                $this->domainEvents->dispatch(new SeoProfileChanged($id, $scope, 'deleted'), $profile->getConnection());
            }

            return $deleted;
        });
    }

    /** @return array{processed:int,nextCursor:string|null} */
    public function import(Authenticatable|SystemActor $actor,
        SeoImportSource $source,
        ?string $cursor = null,
        int $limit = 100,
    ): array {
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Import));
        $limit = max(1, min(500, $limit));
        $page = $source->page($cursor, $limit);

        if (count($page['items']) > $limit) {
            throw new LogicException(
                "The SEO import source returned more than its requested {$limit}-record page.",
            );
        }

        $processed = 0;

        foreach ($page['items'] as $record) {
            $owner = $this->owners->resolve($record['ownerAlias'], $record['ownerId']);
            $this->sync($actor, $owner, $record['profile'], $record['scope']);
            $processed++;
        }

        return [
            'processed' => $processed,
            'nextCursor' => $page['nextCursor'],
        ];
    }
}
