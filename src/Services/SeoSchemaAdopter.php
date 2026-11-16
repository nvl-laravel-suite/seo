<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Enums\SystemActor;
use stdClass;

/**
 * Stages the legacy SEO graph with its original keys for a reviewed host cutover.
 *
 * @api
 *
 * @phpstan-type LegacySource array{connection:?string, scope:string, owner_aliases:array<string,string>}
 * @phpstan-type Review array{schema_fingerprint:string,fingerprint:string,profiles:int,translations:int,staged_profiles:int,staged_translations:int,conflicts:list<string>}
 * @phpstan-type StagedRow array{id:string, ...<string,mixed>}
 */
final readonly class SeoSchemaAdopter
{
    private const array LegacyProfiles = ['id', 'seoable_type', 'seoable_id', 'created_at', 'updated_at'];

    private const array LegacyTranslations = ['id', 'seo_id', 'locale', 'path', 'title', 'description', 'keywords', 'canonical_url', 'additional_data', 'author', 'image_url', 'jsonld_data', 'robots', 'prepend_site_title', 'created_at', 'updated_at'];

    /** Use native owner, translation and mutation policy at the adoption boundary. */
    public function __construct(
        private DatabaseManager $database,
        private SeoOwnerRegistry $owners,
        private SeoAuthorization $authorization,
        private SeoMutationValidator $validator,
        private SeoTranslationNormalizer $normalizer,
    ) {}

    /**
     * Inspect the quiescent legacy `seo` and `seo_i18n` tables without writing.
     * Accepts a source connection, target scope, and legacy morph-to-owner alias map.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function plan(Authenticatable|SystemActor $actor, array $source = []): array
    {
        return $this->inspect($actor, $this->admit($actor, $source));
    }

    /**
     * Stage only reviewed rows in committed profile/translation batches. Matching rows are reusable.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function apply(Authenticatable|SystemActor $actor, string $fingerprint, array $source = []): array
    {
        $options = $this->admit($actor, $source);
        $this->requireCommittedTarget();
        $review = $this->inspect($actor, $options);
        $this->assertReviewed($review, $fingerprint);
        if ($review['staged_profiles'] !== $review['profiles'] || $review['staged_translations'] !== $review['translations']) {
            $this->assertReviewed($this->inspect($actor, $options, write: true), $fingerprint);
            $review = $this->inspect($actor, $options);
        }

        return $this->assertReconciled($review, $fingerprint);
    }

    /**
     * Reconcile committed rows before the host activates native SEO.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function verify(Authenticatable|SystemActor $actor, string $fingerprint, array $source = []): array
    {
        $options = $this->admit($actor, $source);
        $this->requireCommittedTarget();

        return $this->assertReconciled($this->inspect($actor, $options), $fingerprint);
    }

    /**
     * @param  array<array-key,mixed>  $source
     * @return LegacySource
     */
    private function admit(Authenticatable|SystemActor $actor, array $source): array
    {
        if (array_diff(array_keys($source), ['connection', 'scope', 'owner_aliases']) !== []) {
            throw InvalidSeoMutationException::forField('source', 'Unsupported legacy SEO source option.');
        }
        $scope = $source['scope'] ?? null;
        $connection = $source['connection'] ?? null;
        $aliases = $source['owner_aliases'] ?? [];
        if (($scope !== null && ! is_string($scope)) || ($connection !== null && (! is_string($connection) || $connection === '')) || ! is_array($aliases)) {
            throw InvalidSeoMutationException::forField('source', 'The legacy SEO source requires a connection, scope and morph alias map of the declared types.');
        }
        $ownerAliases = [];
        foreach ($aliases as $morph => $alias) {
            if (! is_string($morph) || $morph === '' || ! is_string($alias) || $alias === '') {
                throw InvalidSeoMutationException::forField('owner_aliases', 'Legacy SEO owner aliases must map non-empty morph types to registered aliases.');
            }
            $this->owners->modelClass($alias);
            $ownerAliases[$morph] = $alias;
        }

        $scope = SeoScope::normalize($scope);
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Import, scope: $scope));
        if (SeoOwnership::declared()) {
            throw SeoOwnership::violation('Stage standalone SEO into unowned storage before running native Ownership adoption.');
        }

        return ['connection' => $connection, 'scope' => $scope, 'owner_aliases' => $ownerAliases];
    }

    private function requireCommittedTarget(): void
    {
        if (SeoModelRegistry::newProfile()->getConnection()->transactionLevel() !== 0) {
            throw InvalidSeoMutationException::because('Legacy SEO staging and verification require a committed target outside existing transactions.');
        }
    }

    /**
     * @param  LegacySource  $source
     * @return Review
     */
    private function inspect(Authenticatable|SystemActor $actor, array $source, bool $write = false): array
    {
        $origin = $this->database->connection($source['connection']);
        $model = SeoModelRegistry::newProfile();
        $target = $model->getConnection();
        $profileTable = $model->getTable();
        $translationTable = (new SeoProfileTranslation)->getTable();
        $scope = $source['scope'];
        $schema = [
            $this->schema($origin, 'seo', self::LegacyProfiles, legacy: true),
            $this->schema($origin, 'seo_i18n', self::LegacyTranslations, legacy: true),
            $this->schema($target, $profileTable, ['id', 'scope', 'seoable_type', 'seoable_id'], legacy: false, uniqueness: [['scope', 'seoable_type', 'seoable_id']]),
            $this->schema($target, $translationTable, ['id', 'seo_profile_id', 'scope', 'locale', 'path_hash', 'robots', 'prepend_site_title', 'keywords', 'author'], legacy: false, uniqueness: [['seo_profile_id', 'locale'], ['scope', 'locale', 'path_hash']]),
        ];
        $schemaFingerprint = hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR));
        $digest = hash_init('sha256');
        hash_update($digest, $schemaFingerprint.$scope);

        $conflicts = [];
        $orphan = $origin->table('seo_i18n as t')->useWritePdo()->leftJoin('seo as p', 'p.id', '=', 't.seo_id')->whereNull('p.id')->value('t.id');
        if (is_string($orphan)) {
            $conflicts[] = "Legacy SEO translation [{$orphan}] has no profile.";
        }
        $seenOwners = [];
        $seenRoutes = [];
        $profiles = $translations = $stagedProfiles = $stagedTranslations = 0;

        foreach ($origin->table('seo')->useWritePdo()->lazyById(50)->chunk(50) as $page) {
            $legacyProfiles = $page->all();
            $legacyTranslations = $origin->table('seo_i18n')->useWritePdo()->whereIn('seo_id', array_column($legacyProfiles, 'id'))
                ->orderBy('id')->get()->groupBy('seo_id');
            $profileRows = [];
            $translationRows = [];

            foreach ($legacyProfiles as $legacy) {
                $profile = $this->profile($actor, $legacy, $source);
                $profileId = $profile['id'];
                $ownerKey = json_encode([$profile['seoable_type'], $profile['seoable_id']], JSON_THROW_ON_ERROR);
                if (isset($seenOwners[$ownerKey])) {
                    $conflicts[] = "Legacy SEO profiles [{$seenOwners[$ownerKey]}, {$profileId}] identify the same owner in [{$scope}].";
                }
                $seenOwners[$ownerKey] = $profileId;
                $profileRows[] = $profile;
                hash_update($digest, json_encode([$legacy, $profile], JSON_THROW_ON_ERROR)."\n");

                $seenLocales = [];
                foreach ($legacyTranslations->get($profileId, []) as $legacyTranslation) {
                    $translation = $this->translation($legacyTranslation, $profileId, $scope);
                    if (isset($seenLocales[$translation['locale']])) {
                        $conflicts[] = "Legacy SEO profile [{$profileId}] contains multiple translations for the same locale.";
                    }
                    $seenLocales[$translation['locale']] = true;
                    $pathHash = $translation['path_hash'];
                    if ($pathHash !== null) {
                        if (isset($seenRoutes[$pathHash])) {
                            $path = $translation['path'];
                            $conflicts[] = "Legacy SEO profiles [{$seenRoutes[$pathHash]}, {$profileId}] conflict on route [{$path}].";
                        }
                        $seenRoutes[$pathHash] = $profileId;
                    }
                    $translationRows[] = $translation;
                    hash_update($digest, json_encode([$legacyTranslation, $translation], JSON_THROW_ON_ERROR)."\n");
                }
            }

            $profiles += count($profileRows);
            $translations += count($translationRows);
            $pendingProfiles = $this->pending($target, $profileTable, $profileRows, $conflicts);
            $pendingTranslations = $this->pending($target, $translationTable, $translationRows, $conflicts);
            if ($write && $conflicts !== []) {
                throw InvalidSeoMutationException::because('Legacy SEO has adoption conflicts. '.$conflicts[0]);
            }
            if ($write && ($pendingProfiles !== [] || $pendingTranslations !== [])) {
                $target->transaction(static function () use ($target, $profileTable, $translationTable, $pendingProfiles, $pendingTranslations): void {
                    foreach ([$profileTable => $pendingProfiles, $translationTable => $pendingTranslations] as $table => $rows) {
                        foreach (array_chunk($rows, 40) as $batch) {
                            $target->table($table)->insert($batch);
                        }
                    }
                });
            }
            $stagedProfiles += count($profileRows) - ($write ? 0 : count($pendingProfiles));
            $stagedTranslations += count($translationRows) - ($write ? 0 : count($pendingTranslations));
        }

        if ($target->table($profileTable)->useWritePdo()->where('scope', $scope)->count() !== $stagedProfiles
            || $target->table($translationTable)->useWritePdo()->where('scope', $scope)->count() !== $stagedTranslations) {
            $conflicts[] = "Native SEO scope [{$scope}] contains rows outside the staged legacy graph.";
        }

        return [
            'schema_fingerprint' => $schemaFingerprint, 'fingerprint' => hash_final($digest),
            'profiles' => $profiles, 'translations' => $translations,
            'staged_profiles' => $stagedProfiles, 'staged_translations' => $stagedTranslations, 'conflicts' => $conflicts,
        ];
    }

    /**
     * @param  list<string>  $required
     * @param  list<list<string>>  $uniqueness
     * @return array<string,mixed>
     */
    private function schema(Connection $connection, string $table, array $required, bool $legacy, array $uniqueness = []): array
    {
        $schema = $connection->getSchemaBuilder();
        $columns = $schema->getColumns($table);
        $names = array_column($columns, 'name');
        if (array_diff($required, $names) !== [] || ($legacy && array_diff($names, $required) !== []) || ! $schema->hasIndex($table, ['id'], 'primary')) {
            throw InvalidSeoMutationException::forField('source', "SEO adoption table [{$table}] has an incompatible schema.");
        }
        foreach ($uniqueness as $identity) {
            if (! $schema->hasIndex($table, $identity, 'unique')) {
                throw InvalidSeoMutationException::forField('source', "SEO adoption table [{$table}] lacks a required uniqueness constraint.");
            }
        }

        return [
            'connection' => $connection->getName(), 'database' => $connection->getDatabaseName(), 'driver' => $connection->getDriverName(),
            'table' => $connection->getTablePrefix().$table, 'columns' => $columns, 'indexes' => $schema->getIndexes($table),
            'foreign_keys' => $schema->getForeignKeys($table),
        ];
    }

    /**
     * @param  LegacySource  $source
     * @return array{id:string, seoable_type:string, seoable_id:string, ...<string,mixed>}
     */
    private function profile(Authenticatable|SystemActor $actor, stdClass $legacy, array $source): array
    {
        $id = $this->id($legacy->id);
        if (! is_string($legacy->seoable_type) || ! is_string($legacy->seoable_id)) {
            throw InvalidSeoMutationException::forField('owner', "Legacy SEO profile [{$id}] requires a morph type and owner key.");
        }
        $alias = $source['owner_aliases'][$legacy->seoable_type] ?? $this->owners->aliasForMorphType($legacy->seoable_type);
        $owner = $this->owners->resolveForCleanup($alias, $legacy->seoable_id);
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Create, owner: $owner, ownerAlias: $alias, scope: $source['scope']));

        return [
            'id' => $id, 'scope' => $source['scope'], 'seoable_type' => $owner->getMorphClass(), 'seoable_id' => SeoModelIdentifier::required($owner),
            'is_indexable' => true, 'is_followable' => true, 'max_snippet' => null, 'max_image_preview' => 'large', 'max_video_preview' => null,
            'sitemap_included' => true, 'sitemap_priority' => null, 'sitemap_change_frequency' => null, 'metadata' => null,
            'revision' => 1, 'status' => 'active', 'archived_at' => null, 'created_at' => $this->timestamp($legacy->created_at, $id), 'updated_at' => $this->timestamp($legacy->updated_at, $id),
        ];
    }

    /** @return array{id:string, locale:string, path:string|null, path_hash:string|null, ...<string,mixed>} */
    private function translation(stdClass $legacy, string $profile, string $scope): array
    {
        $id = $this->id($legacy->id);
        if (! is_string($legacy->locale)) {
            throw InvalidSeoMutationException::forField('locale', "Legacy SEO translation [{$id}] requires a locale.");
        }
        $locale = $legacy->locale;
        $attributes = [
            'path' => $legacy->path, 'title' => $legacy->title, 'description' => $legacy->description, 'keywords' => $legacy->keywords,
            'canonicalUrl' => $legacy->canonical_url, 'imageUrl' => $legacy->image_url, 'author' => $legacy->author, 'robots' => $legacy->robots,
            'prependSiteTitle' => $legacy->prepend_site_title,
            'structuredData' => $this->json($legacy->jsonld_data, $id), 'metadata' => $this->json($legacy->additional_data, $id),
        ];
        $this->validator->profile(['translations' => [$locale => $attributes]]);
        $normalized = $this->normalizer->normalize([$locale => $attributes])[$locale];
        $path = is_string($normalized['path']) ? $normalized['path'] : null;
        $prepend = $attributes['prependSiteTitle'];

        return [
            'id' => $id, 'seo_profile_id' => $profile, 'scope' => $scope, 'locale' => $locale,
            'path' => $path, 'title' => $legacy->title, 'description' => $legacy->description, 'keywords' => $legacy->keywords,
            'canonical_url' => $legacy->canonical_url, 'image_url' => $legacy->image_url, 'author' => $legacy->author, 'robots' => $normalized['robots'],
            'path_hash' => SeoPath::hash($scope, $locale, $path), 'prepend_site_title' => $prepend === null ? null : (bool) $prepend,
            'structured_data' => $legacy->jsonld_data,
            'metadata' => $legacy->additional_data,
            'image_reference' => null, 'image_alt' => null, 'open_graph_title' => null, 'open_graph_description' => null,
            'twitter_title' => null, 'twitter_description' => null, 'twitter_card' => null,
            'created_at' => $this->timestamp($legacy->created_at, $id), 'updated_at' => $this->timestamp($legacy->updated_at, $id),
        ];
    }

    private function id(mixed $id): string
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            throw InvalidSeoMutationException::forField('id', 'Legacy SEO records require their original UUIDs.');
        }

        return $id;
    }

    private function timestamp(mixed $value, string $id): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?\z/', $value) !== 1) {
            throw InvalidSeoMutationException::forField('timestamp', "Legacy SEO record [{$id}] has a noncanonical timestamp.");
        }

        return $value;
    }

    /** @return array<array-key,mixed>|null */
    private function json(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        $data = is_string($value) ? json_decode($value, true) : null;
        if (! is_array($data)) {
            throw InvalidSeoMutationException::forField('metadata', "Legacy SEO translation [{$id}] contains unsupported JSON metadata.");
        }

        return $data;
    }

    /**
     * @param  list<StagedRow>  $rows
     * @param  list<string>  $conflicts
     * @return list<StagedRow>
     */
    private function pending(Connection $connection, string $table, array $rows, array &$conflicts): array
    {
        $pending = [];
        foreach (array_chunk($rows, 500) as $batch) {
            $indexed = $connection->table($table)->useWritePdo()->whereIn('id', array_column($batch, 'id'))->get()->keyBy('id');
            foreach ($batch as $expected) {
                $stored = $indexed->get($expected['id']);
                if (! $stored instanceof stdClass) {
                    $pending[] = $expected;

                    continue;
                }
                foreach ($expected as $field => $value) {
                    $actual = $stored->{$field};
                    if (is_bool($value) ? (bool) $actual !== $value : $actual !== $value) {
                        $conflicts[] = "Staged SEO row [{$expected['id']}] differs in [{$field}].";

                        break;
                    }
                }
            }
        }

        return $pending;
    }

    /** @param Review $review */
    private function assertReviewed(array $review, string $fingerprint): void
    {
        if (! hash_equals($review['fingerprint'], $fingerprint)) {
            throw InvalidSeoMutationException::because('Legacy SEO schema or data changed. Review a new adoption plan.');
        }
        if ($review['conflicts'] !== []) {
            throw InvalidSeoMutationException::because('Legacy SEO has adoption conflicts. '.$review['conflicts'][0]);
        }
    }

    /**
     * @param  Review  $review
     * @return Review
     */
    private function assertReconciled(array $review, string $fingerprint): array
    {
        $this->assertReviewed($review, $fingerprint);
        if ($review['profiles'] !== $review['staged_profiles'] || $review['translations'] !== $review['staged_translations']) {
            throw InvalidSeoMutationException::because('Legacy SEO is not fully staged. Resume the reviewed adoption.');
        }

        return $review;
    }
}
