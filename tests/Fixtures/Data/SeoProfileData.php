<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Models\SeoProfile;

/**
 * Privileged management representation of one SEO profile.
 *
 * @api
 */
final class SeoProfileData
{
    /**
     * @param  array<string, SeoProfileTranslationData>  $translations
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $scope,
        public readonly string $ownerAlias,
        public readonly string $ownerMorphType,
        public readonly string $ownerId,
        public readonly string $status,
        public readonly int $revision,
        public readonly bool $isIndexable,
        public readonly bool $isFollowable,
        public readonly ?int $maxSnippet,
        public readonly ?string $maxImagePreview,
        public readonly ?int $maxVideoPreview,
        public readonly bool $sitemapIncluded,
        public readonly ?string $sitemapPriority,
        public readonly ?SitemapChangeFrequency $sitemapChangeFrequency,
        public readonly ?array $metadata,
        public readonly ?string $archivedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $translations,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...get_object_vars($this),
            'sitemapChangeFrequency' => $this->sitemapChangeFrequency?->value,
            'translations' => array_map(
                static fn (SeoProfileTranslationData $translation): array => $translation->toArray(),
                $this->translations,
            ),
        ];
    }

    /**
     * Create the complete management representation for one registered owner alias.
     *
     * @internal
     */
    public static function fromModel(SeoProfile $profile, string $ownerAlias): self
    {
        $profile->loadMissing('translations');
        $translations = [];

        foreach ($profile->translations->sortBy('locale', SORT_STRING) as $translation) {
            $translations[$translation->locale] = SeoProfileTranslationData::fromModel($translation);
        }

        return new self(
            id: $profile->id,
            scope: $profile->scope,
            ownerAlias: $ownerAlias,
            ownerMorphType: $profile->seoable_type,
            ownerId: $profile->seoable_id,
            status: $profile->status,
            revision: $profile->revision,
            isIndexable: $profile->is_indexable,
            isFollowable: $profile->is_followable,
            maxSnippet: $profile->max_snippet,
            maxImagePreview: $profile->max_image_preview,
            maxVideoPreview: $profile->max_video_preview,
            sitemapIncluded: $profile->sitemap_included,
            sitemapPriority: $profile->sitemap_priority,
            sitemapChangeFrequency: $profile->sitemap_change_frequency,
            metadata: $profile->metadata,
            archivedAt: $profile->archived_at?->toAtomString(),
            createdAt: $profile->created_at->toAtomString(),
            updatedAt: $profile->updated_at->toAtomString(),
            translations: $translations,
        );
    }
}
