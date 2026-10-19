<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Nvl\Seo\Contracts\ArchiveSeoProfileContract;
use Nvl\Seo\Events\SeoProfileChanged;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Support\Events\DomainEventDispatcher;

/**
 * Archives or restores a profile without destroying historical metadata.
 *
 * @api
 */
final readonly class ArchiveSeoProfileAction implements ArchiveSeoProfileContract
{
    public function __construct(private SitemapCache $sitemapCache, private DomainEventDispatcher $domainEvents) {}

    public function execute(
        SeoProfile|string $profile,
        bool $archived,
        int $expectedRevision,
    ): SeoProfile {
        $profileId = $profile instanceof SeoProfile ? $profile->id : $profile;

        return (new SeoProfile)->getConnection()->transaction(function () use ($profileId, $archived, $expectedRevision): SeoProfile {
            $profile = SeoProfile::query()
                ->lockForUpdate()
                ->findOrFail($profileId);

            if ($profile->revision !== $expectedRevision) {
                throw StaleSeoProfileException::forProfile($profile->id);
            }

            $profile->status = $archived ? 'archived' : 'active';
            $profile->archived_at = $archived ? now() : null;
            $profile->save();
            $profile->refresh()->load('translations');
            $identity = $this->sitemapCache->capture($profile->scope);
            (new SeoProfile)->getConnection()->afterCommit(function () use ($identity): void {
                $this->sitemapCache->forgetCaptured($identity);
            });
            $this->domainEvents->dispatch(new SeoProfileChanged(
                $profile->id,
                $profile->scope,
                $archived ? 'archived' : 'restored',
            ), $profile->getConnection());

            return $profile;
        });
    }
}
