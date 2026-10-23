<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Contracts\GetOwnerSeoProfileContract;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Data\SeoProfileData;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfilePresenter;
use Nvl\Seo\Support\SeoScope;

/**
 * Returns one complete authorized SEO profile from its owning model.
 *
 * @api
 */
final readonly class GetOwnerSeoProfileAction implements GetOwnerSeoProfileContract
{
    /**
     * Create the owner-centric profile reader.
     */
    public function __construct(
        private SeoAuthorization $authorization,
        private SeoOwnerRegistry $owners,
        private SeoProfilePresenter $presenter,
    ) {}

    /**
     * Return the complete profile projection, or null when the owner has none.
     */
    public function execute(Model $owner, ?string $scope = null): ?SeoProfileData
    {
        $scope = SeoScope::normalize($scope);
        $owner = $this->owners->admitForRead([$owner], $scope, $this->authorization)[0]['owner'];
        $profile = SeoProfile::query()
            ->with('translations')
            ->forOwner($owner, $scope)
            ->first();

        return $profile instanceof SeoProfile
            ? $this->presenter->present($profile)
            : null;
    }
}
