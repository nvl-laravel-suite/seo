<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Support\SeoAuthorizationContext;

/**
 * Gate-backed default that denies access until explicitly configured.
 */
final class ConfiguredSeoAuthorization implements SeoAuthorization
{
    /**
     * Authorize through the configured consumer Gate ability.
     */
    public function authorize(SeoAuthorizationContext $context): void
    {
        $configuredAbility = config('nvl-seo.authorization.ability');

        if (! is_string($configuredAbility) || $configuredAbility === '') {
            throw new AuthorizationException(
                'SEO management requires an authorization binding or configured Gate ability.',
            );
        }

        if (! $context->actor instanceof Authenticatable) {
            throw new AuthorizationException('Public and system SEO operations require an explicit host authorization binding.');
        }
        Gate::forUser($context->actor)->authorize($configuredAbility, [$context]);
    }
}
