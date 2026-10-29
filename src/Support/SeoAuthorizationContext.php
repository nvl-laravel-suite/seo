<?php

declare(strict_types=1);

namespace Nvl\Seo\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Support\Auth\CanonicalActorResolver;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

/**
 * Carries complete source and target context to consumer-owned SEO authorization.
 *
 * @api
 */
final readonly class SeoAuthorizationContext
{
    /** The current persisted principal or explicitly supplied public/system identity. */
    public Authenticatable|SystemActor|PublicActor $actor;

    /**
     * Create one authorization decision context.
     */
    public function __construct(
        Authenticatable|SystemActor|PublicActor $actor,
        public SeoAbility $ability,
        public ?SeoProfile $profile = null,
        public ?Model $owner = null,
        public ?string $ownerAlias = null,
        public ?Model $targetOwner = null,
        public ?string $targetOwnerAlias = null,
        public ?string $scope = null,
        public ?string $redirectSource = null,
        public ?string $redirectLocale = null,
    ) {
        if ($actor instanceof Authenticatable) {
            if (! $actor instanceof Model) {
                throw new AuthorizationException('SEO requires a persisted actor.');
            }
            $actor = app(CanonicalActorResolver::class)->resolve($actor);
        }
        if ($actor instanceof PublicActor && $ability !== SeoAbility::Resolve) {
            throw new AuthorizationException('Anonymous SEO access is limited to explicit public resolution.');
        }
        $this->actor = $actor;
    }
}
