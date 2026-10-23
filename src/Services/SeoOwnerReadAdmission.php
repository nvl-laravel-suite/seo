<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;

/**
 * Admits current scoped owners before exposing owner-centric SEO projections.
 *
 * @internal
 */
final readonly class SeoOwnerReadAdmission
{
    /** Retain the registered owner, policy and active ownership boundaries. */
    public function __construct(
        private OwnershipBoundary $boundary,
        private OwnershipResourceRegistry $ownershipResources,
        private TenantResourceRegistry $tenantResources,
    ) {}

    /**
     * Authorize finite input, reload each native owner class once and admit current facts.
     *
     * @param  iterable<array-key, Model>  $owners
     * @return list<array{owner: Model, alias: string, type: string, id: string}>
     */
    public function admit(iterable $owners, string $scope, SeoOwnerRegistry $registry, SeoAuthorization $authorization): array
    {
        $identities = [];
        foreach ($owners as $owner) {
            if (count($identities) === 100) {
                throw new InvalidArgumentException('SEO profile lists support at most 100 owner entries.');
            }
            if (! $owner->exists) {
                throw new InvalidArgumentException('An SEO owner must be persisted.');
            }
            $identities[] = [
                'owner' => $owner,
                'alias' => $registry->aliasFor($owner),
                'type' => $owner->getMorphClass(),
                'id' => SeoModelIdentifier::required($owner),
            ];
        }
        foreach ($identities as $identity) {
            $this->authorize($authorization, $identity['owner'], $identity['alias'], $scope);
        }

        /** @var array<class-string<Model>, array<string, list<string>>> $groups */
        $groups = [];
        foreach ($identities as $identity) {
            $prototype = new ($identity['owner']::class);
            $connection = $prototype->getConnection()->getName();
            $groups[$prototype::class][$connection][] = $identity['id'];
        }
        /** @var array<class-string<Model>, array<string, array<string, Model>>> $loaded */
        $loaded = [];
        foreach ($groups as $class => $connections) {
            foreach ($connections as $connection => $ids) {
                $prototype = new $class;
                $prototype->setConnection($connection);
                foreach ($prototype->newQuery()->withoutEagerLoads()->whereIn($prototype->getQualifiedKeyName(), $ids)->get() as $canonical) {
                    $loaded[$class][$connection][SeoModelIdentifier::required($canonical)] = $canonical;
                }
            }
        }
        foreach ($identities as $index => $identity) {
            $owner = $identity['owner'];
            $prototype = new ($owner::class);
            $connection = $prototype->getConnection()->getName();
            $canonical = $loaded[$owner::class][$connection][$identity['id']]
                ?? throw (new ModelNotFoundException)->setModel($owner::class, [$identity['id']]);

            if (SeoOwnership::active()) {
                $resource = (SeoOwnership::declared() ? $this->ownershipResources : $this->tenantResources)->forModel($canonical);
                $this->boundary->assertRecord($canonical, $resource->key);
            }
            $originalAttributes = $owner->getAttributes();
            $canonicalAttributes = $canonical->getAttributes();
            ksort($originalAttributes);
            ksort($canonicalAttributes);
            if ($originalAttributes !== $canonicalAttributes || $owner->getRelations() !== []) {
                $this->authorize($authorization, $canonical, $identity['alias'], $scope);
            }
            $identities[$index]['owner'] = $canonical;
            $identities[$index]['type'] = $canonical->getMorphClass();
        }

        return $identities;
    }

    /** Authorize the exact owner model supplied at this admission stage. */
    private function authorize(SeoAuthorization $authorization, Model $owner, string $alias, string $scope): void
    {
        $authorization->authorize(new SeoAuthorizationContext(
            ability: SeoAbility::View,
            owner: $owner,
            ownerAlias: $alias,
            scope: $scope,
        ));
    }
}
