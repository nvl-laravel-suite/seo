<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Contracts\GetOwnerSeoRevisionContract;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Data\SeoOwnerRevisionData;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Support\SeoScope;

/**
 * Returns the optimistic revision identity for one owner's scoped SEO profile.
 *
 * @api
 */
final readonly class GetOwnerSeoRevisionAction implements GetOwnerSeoRevisionContract
{
    /**
     * Create the owner-centric revision reader.
     */
    public function __construct(
        private SeoAuthorization $authorization,
        private SeoOwnerRegistry $owners,
    ) {}

    /**
     * Return revision zero with no profile identifier when no profile exists.
     */
    public function execute(Model $owner, ?string $scope = null): SeoOwnerRevisionData
    {
        $scope = SeoScope::normalize($scope);
        $identity = $this->owners->admitForRead([$owner], $scope, $this->authorization)[0];
        $owner = $identity['owner'];
        $profile = SeoProfile::query()
            ->forOwner($owner, $scope)
            ->first(['id', 'revision']);

        return new SeoOwnerRevisionData(
            ownerAlias: $identity['alias'],
            ownerId: $identity['id'],
            scope: $scope,
            profileId: $profile instanceof SeoProfile ? $profile->id : null,
            revision: $profile instanceof SeoProfile ? $profile->revision : 0,
        );
    }
}
