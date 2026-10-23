<?php

declare(strict_types=1);

namespace Nvl\Seo\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use InvalidArgumentException;
use LogicException;
use Nvl\Seo\Support\ExactSeoOwnerText;

/**
 * A morph-many relation whose foreign identifier is normalized to text.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<TRelatedModel, TDeclaringModel>
 */
final class StringMorphMany extends MorphMany
{
    public function addConstraints(): void
    {
        if (! self::$constraints) {
            return;
        }

        $query = $this->getRelationQuery();
        $identifier = $this->normalizeIdentifier($this->getParentKey());
        $driver = $query->getModel()->getConnection()->getDriverName();
        $query
            ->whereRaw(new ExactSeoOwnerText($this->morphType, $driver), [$this->morphClass, $this->morphClass])
            ->whereRaw(new ExactSeoOwnerText($this->foreignKey, $driver), [$identifier, $identifier])
            ->whereNotNull($this->foreignKey);
    }

    /**
     * @param  array<int, TDeclaringModel>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(
            fn (mixed $key): ?string => $this->normalizeIdentifier($key),
            $this->getKeys($models, $this->localKey),
        );

        $this->whereInEager(
            'whereIn',
            $this->foreignKey,
            array_values(array_filter($keys, static fn (?string $key): bool => $key !== null)),
            $this->getRelationQuery(),
        );

        $query = $this->getRelationQuery();
        $query->whereRaw(new ExactSeoOwnerText($this->morphType, $query->getModel()->getConnection()->getDriverName()), [$this->morphClass, $this->morphClass]);
    }

    /**
     * Limit eager results per exact foreign identifier without changing direct relation limits.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value): static
    {
        parent::limit($value);

        if ($this->parent->exists) {
            return $this;
        }
        $query = $this->getQuery()->getQuery();
        if ($query->groupLimit === null) {
            return $this;
        }
        $grammar = $query->getGrammar();
        if ($grammar instanceof MySqlGrammar && $grammar->useLegacyGroupLimit($query)) {
            throw new LogicException('Exact SEO eager limits require window-function capable MySQL.');
        }
        $query->groupLimit['column'] = new ExactSeoOwnerPartition(
            $this->getExistenceCompareKey(),
            $this->getRelated()->getConnection()->getDriverName(),
        );

        return $this;
    }

    /**
     * @param  array<int, string>|string  $columns
     * @return Builder<TRelatedModel>
     */
    public function getRelationExistenceQuery(
        Builder $query,
        Builder $parentQuery,
        $columns = ['*'],
    ): Builder {
        $parentKey = $this->textColumnExpression($query, $this->getQualifiedParentKeyName());
        $foreignKey = $query->getQuery()->getGrammar()->wrap($this->getExistenceCompareKey());
        $driver = $query->getModel()->getConnection()->getDriverName();

        return $query
            ->select($columns)
            ->whereRaw(new TextColumnComparison($parentKey, $foreignKey, $driver))
            ->whereRaw(new ExactSeoOwnerText($query->qualifyColumn($this->getMorphType()), $driver), [$this->morphClass, $this->morphClass]);
    }

    /**
     * @param  TRelatedModel  $model
     */
    protected function setForeignAttributesForCreate(Model $model): void
    {
        $model->setAttribute(
            $this->getForeignKeyName(),
            $this->normalizeIdentifier($this->getParentKey()),
        );
        $model->setAttribute($this->getMorphType(), $this->morphClass);

        foreach ($this->getQuery()->pendingAttributes as $key => $value) {
            $attributes ??= $model->getAttributes();

            if (! array_key_exists($key, $attributes)) {
                $model->setAttribute($key, $value);
            }
        }

        $this->applyInverseRelationToModel($model);
    }

    private function normalizeIdentifier(mixed $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        if (! is_int($identifier) && ! is_string($identifier)) {
            throw new InvalidArgumentException('SEO owner identifiers must be integers or strings.');
        }

        return (string) $identifier;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function textColumnExpression(Builder $query, string $column): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        return match ($query->getModel()->getConnection()->getDriverName()) {
            'pgsql', 'sqlite' => "CAST({$wrapped} AS TEXT)",
            'sqlsrv' => "CAST({$wrapped} AS NVARCHAR(255))",
            default => "CAST({$wrapped} AS CHAR)",
        };
    }
}
