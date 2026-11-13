<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Contracts\SeoImageResolver;
use Nvl\Seo\Contracts\SitemapArtifactStore;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\HttpUrl;
use Nvl\Seo\Support\SeoScope;
use Throwable;

/**
 * Inspects SEO schema, configuration, routes, and extension bindings without mutation.
 *
 * @internal
 */
final readonly class SeoDoctor
{
    /**
     * Create the SEO readiness inspector.
     */
    public function __construct(
        private Container $container,
        private StructuredDataRegistry $structuredData,
        private SeoOwnerRegistry $owners,
        private Repository $cache,
        private RobotsGenerator $robots,
    ) {}

    /**
     * Return every SEO readiness check.
     *
     * @return list<array{key:string,severity:string,passed:bool,message:string}>
     */
    public function inspect(): array
    {
        return [
            ...$this->schemaChecks(),
            $this->bindingCheck(SeoImageResolver::class, 'binding.image-resolver'),
            $this->bindingCheck(SeoAuthorization::class, 'binding.authorization'),
            $this->bindingCheck(SitemapArtifactStore::class, 'binding.sitemap-artifact-store'),
            $this->siteConfigurationCheck(),
            $this->ownerRegistryCheck(),
            $this->structuredDataCheck(),
            $this->sitemapConfigurationCheck(),
            $this->robotsConfigurationCheck(),
        ];
    }

    /**
     * Safely resolve whether SEO ownership is declared without aborting schema diagnostics on invalid owner configuration.
     */
    private function ownershipDeclared(): bool
    {
        try {
            return SeoOwnership::declared();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Inspect required tables, columns, identifier types, and indexes.
     *
     * @return list<array{key:string,severity:string,passed:bool,message:string}>
     */
    private function schemaChecks(): array
    {
        $owned = $this->ownershipDeclared();
        $profilesTable = SeoTables::get(SeoTables::Profiles);
        $i18nTable = SeoTables::get(SeoTables::I18n);
        $redirectsTable = SeoTables::get(SeoTables::Redirects);
        $profileUniqueIndex = $owned
            ? 'seo_owned_'.substr(hash('sha256', $profilesTable.'scope,seoable_type,seoable_id'), 0, 24)
            : 'seo_profiles_scope_owner_unique';
        $i18nRouteUniqueIndex = $owned
            ? 'seo_owned_'.substr(hash('sha256', $i18nTable.'scope,locale,path_hash'), 0, 24)
            : 'seo_profiles_i18n_route_unique';
        $redirectSourceUniqueIndex = $owned
            ? 'seo_owned_'.substr(hash('sha256', $redirectsTable.'source_hash'), 0, 24)
            : 'seo_redirects_source_hash_unique';

        $tables = [
            $profilesTable => [
                'columns' => [
                    'id', 'scope', 'seoable_type', 'seoable_id', 'revision',
                    'status', 'archived_at', 'is_indexable', 'is_followable',
                    'max_snippet', 'max_image_preview', 'max_video_preview',
                    'sitemap_included', 'sitemap_priority',
                    'sitemap_change_frequency', 'metadata', 'created_at',
                    'updated_at',
                    ...($owned ? ['owner_scope_key'] : []),
                ],
                'indexes' => [
                    $profileUniqueIndex => true,
                    'seo_profiles_owner_index' => false,
                    'seo_profiles_sitemap_scan_index' => false,
                ],
            ],
            $i18nTable => [
                'columns' => [
                    'id', 'seo_profile_id', 'scope', 'locale', 'path',
                    'path_hash', 'title', 'description', 'canonical_url',
                    'image_url', 'image_reference', 'image_alt',
                    'open_graph_title', 'open_graph_description',
                    'twitter_title', 'twitter_description', 'twitter_card',
                    'structured_data', 'metadata', 'created_at', 'updated_at',
                    ...($owned ? ['owner_scope_key'] : []),
                ],
                'indexes' => [
                    'seo_profiles_i18n_owner_locale_unique' => true,
                    $i18nRouteUniqueIndex => true,
                ],
            ],
            $redirectsTable => [
                'columns' => [
                    'id', 'scope', 'locale', 'source_path', 'source_hash',
                    'target', 'status_code', 'revision', 'is_active',
                    'expires_at', 'hit_count', 'last_hit_at', 'metadata',
                    'created_at', 'updated_at', 'deleted_at',
                    ...($owned ? ['owner_scope_key'] : []),
                ],
                'indexes' => [
                    $redirectSourceUniqueIndex => true,
                ],
            ],
            SeoTables::get(SeoTables::RedirectLocks) => [
                'columns' => ['name'],
                'indexes' => [],
            ],
        ];

        if ($owned) {
            $tables[SeoTables::get(SeoTables::OwnerRedirectLocks)] = [
                'columns' => ['name'],
                'indexes' => [],
            ];
        }

        $checks = [];

        try {
            $connection = SeoModelRegistry::newProfile()->getConnection();
        } catch (Throwable $exception) {
            foreach (array_keys($tables) as $table) {
                $checks[] = [
                    'key' => "schema.table.{$table}",
                    'severity' => 'error',
                    'passed' => false,
                    'message' => 'The SEO database is unavailable: '.$exception->getMessage(),
                ];
            }

            return $checks;
        }

        $schema = $connection->getSchemaBuilder();

        foreach ($tables as $table => $requirements) {
            try {
                $exists = $this->probe($connection, static fn (): bool => $schema->hasTable($table));
            } catch (Throwable $exception) {
                $checks[] = [
                    'key' => "schema.table.{$table}",
                    'severity' => 'error',
                    'passed' => false,
                    'message' => 'The SEO database is unavailable: '.$exception->getMessage(),
                ];

                continue;
            }

            $checks[] = [
                'key' => "schema.table.{$table}",
                'severity' => 'error',
                'passed' => $exists,
                'message' => $exists ? "Table [{$table}] exists." : "Table [{$table}] is missing.",
            ];

            if (! $exists) {
                continue;
            }

            try {
                $existingColumns = $this->probe($connection, static fn (): array => $schema->getColumnListing($table));
            } catch (Throwable $exception) {
                $checks[] = [
                    'key' => "schema.columns.{$table}",
                    'severity' => 'error',
                    'passed' => false,
                    'message' => 'Could not inspect columns: '.$exception->getMessage(),
                ];

                continue;
            }

            $missing = array_values(array_diff($requirements['columns'], $existingColumns));
            $checks[] = [
                'key' => "schema.columns.{$table}",
                'severity' => 'error',
                'passed' => $missing === [],
                'message' => $missing === []
                    ? "Required columns exist on [{$table}]."
                    : 'Missing columns: '.implode(', ', $missing).'.',
            ];

            if (! in_array('id', $existingColumns, true)) {
                continue;
            }

            $idType = $this->probe($connection, static fn (): string => $schema->getColumnType($table, 'id'));
            $checks[] = [
                'key' => "schema.id-type.{$table}",
                'severity' => 'error',
                'passed' => in_array($idType, ['uuid', 'guid', 'char', 'varchar', 'string'], true),
                'message' => "Identifier column on [{$table}] uses [{$idType}].",
            ];
            $indexes = collect($this->probe($connection, static fn (): array => $schema->getIndexes($table)));

            foreach ($requirements['indexes'] as $name => $unique) {
                $index = null;

                foreach ($indexes as $candidate) {
                    if ($candidate['name'] === $name) {
                        $index = $candidate;

                        break;
                    }
                }
                $checks[] = [
                    'key' => "schema.index.{$name}",
                    'severity' => 'error',
                    'passed' => is_array($index)
                        && (! $unique || $index['unique'] === true),
                    'message' => is_array($index)
                        ? "Index [{$name}] exists."
                        : "Index [{$name}] is missing.",
                ];
            }

            if ($table === $redirectsTable) {
                $targetIndexExists = $indexes->contains(
                    static fn (array $index): bool => $index['name']
                        === 'seo_redirects_target_index',
                );
                $checks[] = [
                    'key' => 'schema.index.redirect-target-text',
                    'severity' => 'error',
                    'passed' => ! $targetIndexExists,
                    'message' => $targetIndexExists
                        ? 'The non-portable redirect target TEXT index still exists.'
                        : 'No non-portable redirect target TEXT index exists.',
                ];

                if (in_array('source_hash', $existingColumns, true)) {
                    $invalidHashesExist = $this->probe(
                        $connection,
                        static fn (): bool => $connection->table($table)
                            ->whereNull('source_hash')
                            ->orWhere('source_hash', '')
                            ->orWhereRaw('LENGTH(source_hash) <> 64')
                            ->exists(),
                    );
                    $checks[] = [
                        'key' => 'schema.redirect-source-hashes',
                        'severity' => 'error',
                        'passed' => ! $invalidHashesExist,
                        'message' => $invalidHashesExist
                            ? 'Redirect source hashes contain null, empty, or invalid-width values.'
                            : 'Redirect source hashes use the required fixed-width identity.',
                    ];
                    $duplicateColumns = $owned && in_array('owner_scope_key', $existingColumns, true)
                        ? ['owner_scope_key', 'source_hash']
                        : ['source_hash'];
                    $duplicatesExist = $this->probe(
                        $connection,
                        static fn (): bool => $connection->table($table)
                            ->select($duplicateColumns)
                            ->whereNotNull('source_hash')
                            ->groupBy($duplicateColumns)
                            ->havingRaw('COUNT(*) > 1')
                            ->exists(),
                    );
                    $checks[] = [
                        'key' => 'schema.redirect-duplicate-identities',
                        'severity' => 'error',
                        'passed' => ! $duplicatesExist,
                        'message' => $duplicatesExist
                            ? 'Duplicate redirect source hashes must be resolved.'
                            : 'No duplicate redirect source hashes exist.',
                    ];
                }
            }

            if ($table === $profilesTable
                && in_array('revision', $existingColumns, true)
                && in_array('status', $existingColumns, true)) {
                $invalidProfilesExist = $this->probe(
                    $connection,
                    static fn (): bool => $connection->table($table)
                        ->where('revision', '<', 1)
                        ->orWhereNotIn('status', ['active', 'archived'])
                        ->exists(),
                );
                $checks[] = [
                    'key' => 'schema.profile-row-integrity',
                    'severity' => 'error',
                    'passed' => ! $invalidProfilesExist,
                    'message' => $invalidProfilesExist
                        ? 'SEO profiles contain invalid revisions or lifecycle statuses.'
                        : 'SEO profile revisions and lifecycle statuses are valid.',
                ];
            }
        }

        return $checks;
    }

    /**
     * Execute a read-only database probe inside a savepoint when an outer transaction is active.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    private function probe(ConnectionInterface $connection, callable $operation): mixed
    {
        return $connection->transactionLevel() > 0
            ? $connection->transaction(static fn () => $operation())
            : $operation();
    }

    /**
     * Inspect one required container binding.
     *
     * @param  class-string  $contract
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function bindingCheck(string $contract, string $key): array
    {
        if (! $this->container->bound($contract)) {
            return [
                'key' => $key,
                'severity' => 'error',
                'passed' => false,
                'message' => "Contract [{$contract}] is missing.",
            ];
        }

        try {
            $resolved = $this->container->make($contract);
            $healthy = $resolved instanceof $contract;

            return [
                'key' => $key,
                'severity' => 'error',
                'passed' => $healthy,
                'message' => $healthy
                    ? "Contract [{$contract}] resolves to a compatible implementation."
                    : "Contract [{$contract}] resolves to an incompatible implementation.",
            ];
        } catch (Throwable $exception) {
            return [
                'key' => $key,
                'severity' => 'error',
                'passed' => false,
                'message' => "Contract [{$contract}] cannot be resolved: {$exception->getMessage()}",
            ];
        }
    }

    /**
     * Validate the configured site URL.
     *
     *
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function siteConfigurationCheck(): array
    {
        $baseUrl = config('nvl-seo.site.base_url');
        $healthy = is_string($baseUrl) && HttpUrl::isBase($baseUrl);

        return [
            'key' => 'configuration.site',
            'severity' => 'error',
            'passed' => $healthy,
            'message' => $healthy
                ? 'The SEO base URL is an absolute HTTP(S) URL without query or fragment.'
                : 'seo.site.base_url must be an absolute HTTP(S) URL without query or fragment.',
        ];
    }

    /**
     * Validate every owner alias registration.
     *
     *
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function ownerRegistryCheck(): array
    {
        try {
            $owners = $this->owners->configured();
            $registeredMorphTypes = [];

            foreach ($owners as $modelClass) {
                $registeredMorphTypes[] = (new $modelClass)->getMorphClass();
            }

            $profilesTable = SeoTables::get(SeoTables::Profiles);
            $connection = SeoModelRegistry::newProfile()->getConnection();
            $schema = $connection->getSchemaBuilder();
            $storedMorphTypes = $this->probe(
                $connection,
                static fn (): array => $schema->hasTable($profilesTable) && $schema->hasColumn($profilesTable, 'seoable_type')
                    ? $connection->table($profilesTable)
                        ->distinct()
                        ->orderBy('seoable_type')
                        ->pluck('seoable_type')
                        ->filter(static fn (mixed $value): bool => is_string($value))
                        ->values()
                        ->all()
                    : [],
            );
            $missing = array_values(array_diff($storedMorphTypes, $registeredMorphTypes));

            return [
                'key' => 'configuration.owners',
                'severity' => $missing === []
                    || ! (bool) config('nvl-seo.management.enabled', false)
                    ? 'warning'
                    : 'error',
                'passed' => $missing === [],
                'message' => $missing === []
                    ? count($owners).' unique management owner aliases are registered.'
                    : 'Stored owner morph types have no management alias: '
                        .implode(', ', $missing).'.',
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'key' => 'configuration.owners',
                'severity' => 'error',
                'passed' => false,
                'message' => $exception->getMessage(),
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'configuration.owners',
                'severity' => 'error',
                'passed' => false,
                'message' => 'Owner aliases could not be inspected: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Validate structured-data mode and report registered providers.
     *
     *
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function structuredDataCheck(): array
    {
        $mode = config('nvl-seo.structured_data.mode', 'merge');
        $limits = [
            config('nvl-seo.structured_data.maximum_bytes'),
            config('nvl-seo.structured_data.maximum_depth'),
            config('nvl-seo.structured_data.maximum_items'),
        ];
        $healthy = is_string($mode)
            && in_array($mode, ['persisted', 'generated', 'merge'], true)
            && collect($limits)->every(
                static fn (mixed $limit): bool => is_int($limit) && $limit > 0,
            );

        return [
            'key' => 'configuration.structured-data',
            'severity' => 'error',
            'passed' => $healthy,
            'message' => $healthy
                ? count($this->structuredData->keys())
                    ." resource-aware providers are registered in [{$mode}] mode."
                : 'Structured-data mode and limits must be valid positive values.',
        ];
    }

    /**
     * Validate portable sitemap limits and atomic build locking.
     *
     *
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function sitemapConfigurationCheck(): array
    {
        $maximumUrls = config('nvl-seo.sitemap.max_urls');
        $maximumBytes = config('nvl-seo.sitemap.max_bytes');
        $cacheSeconds = config('nvl-seo.sitemap.cache_seconds');
        $artifactDirectory = config('nvl-seo.sitemap.directory');
        $artifactDisk = config('nvl-seo.sitemap.disk');
        $needsLock = is_int($cacheSeconds) && $cacheSeconds > 0;
        $publicScopesAreValid = true;

        try {
            SeoScope::publicSitemapScopes();
        } catch (Throwable) {
            $publicScopesAreValid = false;
        }

        $healthy = is_int($maximumUrls)
            && $maximumUrls > 0
            && $maximumUrls <= 50_000
            && is_int($maximumBytes)
            && $maximumBytes > 0
            && $maximumBytes <= 52_428_800
            && is_int($cacheSeconds)
            && $cacheSeconds >= 0
            && is_string($artifactDirectory)
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#', $artifactDirectory) === 1
            && ! str_contains($artifactDirectory, '..')
            && ($artifactDisk === null || (is_string($artifactDisk) && $artifactDisk !== ''))
            && (! $needsLock || $this->cache->getStore() instanceof LockProvider)
            && $publicScopesAreValid;

        return [
            'key' => 'configuration.sitemap',
            'severity' => 'error',
            'passed' => $healthy,
            'message' => $healthy
                ? 'Sitemap URL, byte, artifact-store, cache, and atomic-lock settings are valid.'
                : 'Sitemap limits, artifact storage, or atomic cache locking are invalid.',
        ];
    }

    /**
     * Validate robots output, cache duration, and protocol byte limit.
     *
     *
     * @return array{key:string,severity:string,passed:bool,message:string}
     */
    private function robotsConfigurationCheck(): array
    {
        $cacheSeconds = config('nvl-seo.robots.cache_seconds');
        $maximumBytes = config('nvl-seo.robots.maximum_bytes');
        $healthy = is_int($cacheSeconds)
            && $cacheSeconds >= 0
            && is_int($maximumBytes)
            && $maximumBytes > 0
            && $maximumBytes <= 512_000;
        $message = 'Robots directives, cache duration, and byte limit are valid.';

        if ($healthy) {
            try {
                $this->robots->generate();
            } catch (Throwable $exception) {
                $healthy = false;
                $message = $exception->getMessage();
            }
        } else {
            $message = 'Robots cache duration and byte limit must use valid integer bounds.';
        }

        return [
            'key' => 'configuration.robots',
            'severity' => 'error',
            'passed' => $healthy,
            'message' => $message,
        ];
    }
}
