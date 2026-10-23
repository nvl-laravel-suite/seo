<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Nvl\Seo\Contracts\ListSeoProfilesContract;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Data\SeoProfileData;
use Nvl\Seo\Data\SeoProfileQuery;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfilePresenter;
use Nvl\Seo\Support\ExactSeoOwnerText;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoScope;

/**
 * Lists profiles through a fixed, allowlisted query surface.
 *
 * @api
 */
final class ListSeoProfilesAction implements ListSeoProfilesContract
{
    /**
     * Create the profile-list action.
     */
    public function __construct(
        private readonly SeoAuthorization $authorization,
        private readonly SeoOwnerRegistry $owners,
        private readonly SeoProfilePresenter $presenter,
    ) {}

    /**
     * Return one authorized, bounded page of management profiles.
     *
     * @return LengthAwarePaginator<int, SeoProfileData>
     */
    public function execute(SeoProfileQuery $query): LengthAwarePaginator
    {
        $scope = $query->scope === null ? null : SeoScope::normalize($query->scope);
        if ($query->ownerId !== null && $query->ownerAlias === null) {
            throw new InvalidArgumentException('An SEO owner identity filter requires its registered owner alias.');
        }
        $ownerId = $query->ownerId === null ? null : SeoModelIdentifier::normalize($query->ownerId);
        $owner = $ownerId === null
            ? null : $this->owners->resolve($query->ownerAlias, $ownerId);
        $ownerId = $owner === null ? null : SeoModelIdentifier::required($owner);
        $this->authorization->authorize(new SeoAuthorizationContext(
            ability: SeoAbility::List,
            owner: $owner,
            ownerAlias: $query->ownerAlias,
            scope: $scope,
        ));
        $ownerMorphType = $query->ownerAlias === null
            ? null
            : $this->owners->morphTypeForAlias($query->ownerAlias);

        $profiles = SeoProfile::query()
            ->with('translations')
            ->when(
                $scope !== null,
                static fn ($builder) => $builder->where('scope', $scope),
            )
            ->when(
                $query->status !== null,
                static fn ($builder) => $builder->where('status', $query->status),
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
                perPage: min(200, max(1, $query->perPage)),
                page: max(1, $query->page),
            );

        return $page->through(
            fn (SeoProfile $profile): SeoProfileData => $this->presenter->present($profile),
        );
    }

    /**
     * Keep host identity filtering byte-exact under the package connection's native collation.
     *
     * @param  Builder<SeoProfile>  $query
     * @return Builder<SeoProfile>
     */
    private function exactOwnerText(Builder $query, string $column, string $value): Builder
    {
        $connection = $query->getModel()->getConnection();

        return $query->whereRaw(new ExactSeoOwnerText($column, $connection->getDriverName()), [$value, $value]);
    }
}
