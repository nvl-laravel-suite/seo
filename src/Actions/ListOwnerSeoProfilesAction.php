<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Contracts\ListOwnerSeoProfilesContract;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Data\SeoProfileData;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfilePresenter;
use Nvl\Seo\Support\SeoScope;

/**
 * Returns bounded authorized SEO profiles for an ordered owner collection.
 *
 * @api
 */
final readonly class ListOwnerSeoProfilesAction implements ListOwnerSeoProfilesContract
{
    /**
     * Create the bounded owner-profile reader.
     */
    public function __construct(
        private SeoAuthorization $authorization,
        private SeoOwnerRegistry $owners,
        private SeoProfilePresenter $presenter,
    ) {}

    /**
     * Return one profile or null for each owner in the same positional order.
     *
     * @param  iterable<array-key, Model>  $owners
     * @return list<SeoProfileData|null>
     */
    public function execute(iterable $owners, ?string $scope = null): array
    {
        $scope = SeoScope::normalize($scope);
        $normalized = $this->owners->admitForRead($owners, $scope, $this->authorization);

        if ($normalized === []) {
            return [];
        }

        /** @var array<string, array<string, true>> $identifiersByType */
        $identifiersByType = [];

        foreach ($normalized as $identity) {
            $identifiersByType[$identity['type']][$identity['id']] = true;
        }

        $profiles = SeoProfile::query()
            ->with('translations')
            ->where('scope', $scope)
            ->where(static function (Builder $query) use ($identifiersByType): void {
                foreach ($identifiersByType as $type => $identifiers) {
                    $query->orWhere(static function (Builder $query) use (
                        $type,
                        $identifiers,
                    ): void {
                        $query
                            ->where('seoable_type', $type)
                            ->whereIn('seoable_id', array_keys($identifiers));
                    });
                }
            })
            ->get();
        /** @var array<string, array<string, SeoProfile>> $profilesByType */
        $profilesByType = [];

        foreach ($profiles as $profile) {
            $profilesByType[$profile->seoable_type][$profile->seoable_id] = $profile;
        }

        return array_map(
            fn (array $identity): ?SeoProfileData => isset(
                $profilesByType[$identity['type']][$identity['id']],
            )
                ? $this->presenter->present(
                    $profilesByType[$identity['type']][$identity['id']],
                )
                : null,
            $normalized,
        );
    }
}
