<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use Nvl\Seo\Contracts\FindSeoRedirectContract;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Data\SeoRedirectData;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Contracts\LocaleCatalog;

/**
 * Reads exact redirect management state without runtime hit or fallback effects.
 *
 * @api
 */
final readonly class FindSeoRedirectAction implements FindSeoRedirectContract
{
    /** Resolve management authorization and the shared locale catalogue. */
    public function __construct(private SeoAuthorization $authorization, private LocaleCatalog $locales) {}

    /** Return the current non-deleted management identity, including inactive and expired redirects. */
    public function execute(string $source, ?string $scope = null, ?string $locale = null): ?SeoRedirectData
    {
        $scope = SeoScope::normalize($scope);
        $locale = $locale === null ? null : $this->locales->assertSupported($locale);
        $source = SeoPath::normalize($source) ?? '/';
        $this->authorization->authorize(new SeoAuthorizationContext(
            ability: SeoAbility::ManageRedirects, scope: $scope,
            redirectSource: $source, redirectLocale: $locale,
        ));
        $redirect = SeoRedirect::query()
            ->where('source_hash', SeoRedirect::sourceHash($scope, $locale, $source))
            ->first();

        return $redirect === null ? null : new SeoRedirectData(
            id: $redirect->id, scope: $redirect->scope, locale: $redirect->locale,
            source: $redirect->source_path, target: $redirect->target,
            statusCode: $redirect->status_code, revision: $redirect->revision,
            isActive: $redirect->is_active, expiresAt: $redirect->expires_at?->toIso8601String(),
            metadata: $redirect->metadata,
        );
    }
}
