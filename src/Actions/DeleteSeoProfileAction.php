<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Illuminate\Support\Facades\DB;
use Nvl\Seo\Contracts\DeleteSeoProfileContract;
use Nvl\Seo\Events\SeoProfileChanged;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Deletes one SEO profile and its translations atomically.
 *
 * @api
 */
final readonly class DeleteSeoProfileAction implements DeleteSeoProfileContract
{
    public function __construct(
        private SitemapCache $sitemapCache,
        private DomainEventDispatcher $domainEvents,
    ) {}

    public function execute(SeoProfile|string $profile, ?int $expectedRevision = null): bool
    {
        $profileId = $profile instanceof SeoProfile ? $profile->id : $profile;

        return (new SeoProfile)->getConnection()->transaction(function () use ($profileId, $expectedRevision): bool {
            $profile = SeoProfile::query()
                ->lockForUpdate()
                ->findOrFail($profileId);

            if ($expectedRevision !== null && $profile->revision !== $expectedRevision) {
                throw StaleSeoProfileException::forProfile($profile->id);
            }

            $id = $profile->id;
            $scope = $profile->scope;
            $profile->translations()->delete();
            $deleted = (bool) $profile->delete();

            if ($deleted) {
                $identity = $this->sitemapCache->capture($scope);
                DB::afterCommit(function () use ($identity): void {
                    $this->sitemapCache->forgetCaptured($identity);
                });
                $this->domainEvents->dispatch(new SeoProfileChanged($id, $scope, 'deleted'), $profile->getConnection());
            }

            return $deleted;
        });
    }
}
