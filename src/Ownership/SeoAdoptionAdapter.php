<?php

declare(strict_types=1);

namespace Nvl\Seo\Ownership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Contracts\OwnershipAdoptionAdapter;
use Nvl\Ownership\Services\OwnershipAdoptionStore;
use Nvl\Ownership\Services\OwnershipDatabaseConstraints;
use Nvl\Ownership\Services\OwnershipResourceBackfill;
use Nvl\Ownership\Services\OwnershipSchema;
use Nvl\Ownership\ValueObjects\OwnershipAdoptionPlan;
use Nvl\Ownership\ValueObjects\OwnershipBackfillResult;
use Nvl\Ownership\ValueObjects\OwnershipMapping;
use Nvl\Ownership\ValueObjects\OwnershipVerification;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use stdClass;

/**
 * Adopts exact reviewed SEO records without guessing primary owners from associations.
 *
 * @internal
 */
final readonly class SeoAdoptionAdapter implements OwnershipAdoptionAdapter
{
    /** Retain the canonical services and immutable inputs for this workflow. */
    public function __construct(
        private OwnershipResourceRegistry $resources,
        private OwnershipAdoptionStore $mappings,
        private OwnershipSchema $schema,
        private OwnershipDatabaseConstraints $constraints,
    ) {}

    /** @return list<string> */
    public function resources(): array
    {
        return ['seo.profiles', 'seo.translations', 'seo.redirects'];
    }

    /** Prepare additive partitions and replace only the package's known global receipt identity. */
    public function prepare(OwnershipAdoptionPlan $plan): void
    {
        $root = new ($this->resources->get('seo.profiles')->model);
        $locks = SeoTables::get(SeoTables::OwnerRedirectLocks);
        $schema = $root->getConnection()->getSchemaBuilder();
        if (! $schema->hasTable($locks)) {
            $schema->create($locks, static fn (Blueprint $table) => $table->string('name', 32)->primary());
        }

        foreach ($this->resources() as $key) {
            $model = new ($this->resources->get($key)->model);
            $schema = $model->getConnection()->getSchemaBuilder();
            foreach (['tenant_id', 'ownership_key'] as $column) {
                if ($schema->hasColumn($model->getTable(), $column)) {
                    $schema->table($model->getTable(), static fn (Blueprint $table) => ($column === 'tenant_id' ? $table->uuid($column) : $table->string($column, 191))->nullable()->change());
                }
            }
            $this->schema->prepare($key);
            $identities = match ($key) {
                'seo.profiles' => [['scope', 'seoable_type', 'seoable_id']],
                'seo.translations' => [['scope', 'locale', 'path_hash']],
                default => [['source_hash']],
            };
            foreach ($schema->getIndexes($model->getTable()) as $index) {
                $columns = $index['columns'];
                $withoutTenant = array_values(array_diff($columns, ['tenant_id']));
                if ($index['unique'] && in_array($withoutTenant, $identities, true)) {
                    $schema->table($model->getTable(), static fn (Blueprint $table) => $table->dropUnique($index['name']));
                }
            }
            foreach ($identities as $identity) {
                $this->unique($model, ['owner_scope_key', ...$identity], 'seo_owned_'.substr(hash('sha256', $model->getTable().implode(',', $identity)), 0, 24));
            }
        }
        $this->restoreNativeParents();
    }

    /** Restore the concrete native FK when a legacy adapter replaced it with a tenant composite.
     * @param  list<array{string,string}>  $extraEdges
     */
    private function restoreNativeParents(array $extraEdges = []): void
    {
        foreach ($this->resources() as $key) {
            $definition = $this->resources->get($key);
            if ($definition->ownership->strategy !== 'inherited' || count($definition->parents()) !== 1) {
                continue;
            }
            $model = new $definition->model;
            $relationName = $definition->ownership->relation ?? throw new OwnershipConfigurationInvalid('Inherited ownership requires a concrete parent relation.');
            $relation = $model->{$relationName}();
            if (! $relation instanceof BelongsTo) {
                throw new OwnershipConfigurationInvalid('Inherited ownership requires a canonical native parent relation.');
            }
            if ($relation instanceof MorphTo) {
                continue;
            }
            $parent = new ($this->resources->get($definition->parents()[0])->model);
            $this->restoreNativeForeign($model, $relation->getForeignKeyName(), $parent);
        }
        foreach ($extraEdges as [$resource, $column]) {
            $model = new ($this->resources->get($resource)->model);
            $this->restoreNativeForeign($model, $column, $model);
        }
    }

    /** Preserve existing keys and add only a missing exact native parent constraint. */
    private function restoreNativeForeign(Model $model, string $column, Model $parent): void
    {
        if ($model->getConnection() !== $parent->getConnection()) {
            throw new OwnershipConfigurationInvalid('Inherited package records require the same actual native source.');
        }
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ($schema->getForeignKeys($model->getTable()) as $foreign) {
            if ($foreign['columns'] === [$column]
                && $foreign['foreign_table'] === $parent->getConnection()->getTablePrefix().$parent->getTable()
                && $foreign['foreign_columns'] === [$parent->getKeyName()]) {
                return;
            }
        }
        $name = 'nvl_native_'.substr(hash('sha256', $model->getTable().$column), 0, 24);
        $schema->table($model->getTable(), static fn (Blueprint $table) => $table->foreign([$column], $name)->references([$parent->getKeyName()])->on($parent->getTable())->restrictOnDelete());
    }

    /** @param list<string> $columns */
    private function unique(Model $model, array $columns, string $name): void
    {
        $schema = $model->getConnection()->getSchemaBuilder();
        foreach ($schema->getIndexes($model->getTable()) as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return;
            }
        }
        $schema->table($model->getTable(), static fn (Blueprint $table) => $table->unique($columns, $name));
    }

    /** Backfill bounded actual records from explicit immutable per-record mappings. */
    public function backfill(OwnershipAdoptionPlan $plan, ?string $cursor, int $limit): OwnershipBackfillResult
    {
        return (new OwnershipResourceBackfill($this->resources, $this->mappings))->run($plan, $this->resources(), $cursor, $limit, 'SEO', static function (string $key, Model $model, stdClass $row, OwnershipMapping $mapping): array {
            $owner = $mapping->owner;
            $attributes = [];
            if ($key === 'seo.redirects') {
                if (! is_string($row->scope) || ! is_string($row->source_path) || ($row->locale !== null && ! is_string($row->locale))) {
                    throw new OwnershipConfigurationInvalid('SEO redirects require their canonical scope, locale and source path.');
                }
                $attributes['source_hash'] = SeoRedirect::sourceHashForTenant('*', $row->scope, $row->locale, $row->source_path);
            }

            return $attributes;
        });
    }

    /** Verify persisted partitions and the complete canonical domain graph. */
    public function verify(OwnershipAdoptionPlan $plan): OwnershipVerification
    {
        foreach ($this->resources() as $key) {
            $this->schema->verify($key);
        }
        $profile = new ($this->resources->get('seo.profiles')->model);
        $translation = new ($this->resources->get('seo.translations')->model);
        if ($profile->getConnection()->table($translation->getTable().' as child')->join($profile->getTable().' as parent', 'parent.id', '=', 'child.seo_profile_id')->whereColumn('parent.scope', '!=', 'child.scope')->exists()) {
            throw new OwnershipConfigurationInvalid('SEO translations must retain the canonical profile site scope.');
        }

        return new OwnershipVerification([]);
    }

    /** Constrain every native parent and scope edge before activation. */
    public function activate(OwnershipAdoptionPlan $plan): void
    {
        foreach ($this->resources() as $key) {
            $this->schema->constrain($key);
        }
        $profile = new ($this->resources->get('seo.profiles')->model);
        $translation = new ($this->resources->get('seo.translations')->model);
        $this->constraints->reference($profile->getConnection(), $translation->getTable(), 'seo_profile_id', $profile->getTable(), 'id', sameColumns: ['scope']);
        $targets = [];
        foreach ($this->resources->get('seo.profiles')->parents() as $key) {
            $parent = new ($this->resources->get($key)->model);
            $targets[] = ['type' => $parent->getMorphClass(), 'table' => $parent->getTable(), 'key' => $parent->getKeyName()];
        }
        $this->constraints->polymorphicReference($profile->getConnection(), $profile->getTable(), 'seoable_type', 'seoable_id', $targets);
    }
}
