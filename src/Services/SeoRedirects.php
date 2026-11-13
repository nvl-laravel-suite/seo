<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Enums\SeoAbility;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Exceptions\StaleSeoRedirectException;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Support\DatabaseConstraintViolation;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Support\SeoRedirectTarget;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Native redirect graph mutations, exact management reads and public resolution.
 *
 * @api
 */
final readonly class SeoRedirects
{
    public function __construct(private SeoRedirectChain $chains, private LocaleCatalog $locales, private SeoMutationValidator $validator, private SeoAuthorization $authorization, private SeoRedirectLookup $redirects) {}

    private function lockGraph(): void
    {
        $container = Container::getInstance();
        if (SeoOwnership::declared()) {
            $connection = (SeoModelRegistry::newRedirect())->getConnection();
            $name = substr(hash('sha256', $container->make(OwnershipBoundary::class)->key('seo.redirects', 'graph')), 0, 32);
            $connection->table(SeoTables::get(SeoTables::OwnerRedirectLocks))->insertOrIgnore(['name' => $name]);
            if ($connection->table(SeoTables::get(SeoTables::OwnerRedirectLocks))->where('name', $name)->lockForUpdate()->first() === null) {
                throw new OwnershipViolation('The canonical SEO redirect lock could not be acquired.');
            }

            return;
        }
        $connection = (SeoModelRegistry::newRedirect())->getConnection();
        $connection->table(SeoTables::get(SeoTables::RedirectLocks))->insertOrIgnore(['name' => 'graph']);
        $connection->table(SeoTables::get(SeoTables::RedirectLocks))->where('name', 'graph')->lockForUpdate()->first();
    }

    /** @param array{sourcePath:string,target:string,locale?:string|null,statusCode?:int,isActive?:bool,expiresAt?:string|null,metadata?:array<string,mixed>|null,expected_revision?:int|null} $data */
    public function sync(Authenticatable|SystemActor $actor,
        SeoRedirect|string|null $redirect,
        array $data,
        ?string $scope = null,
    ): SeoRedirect {
        $data += ['locale' => null, 'statusCode' => 301, 'isActive' => true, 'expiresAt' => null];
        $this->validator->redirect($data);
        $scope = SeoScope::normalize($scope);
        $locale = $data['locale'] === null
            ? null
            : $this->locales->assertSupported($data['locale']);
        $source = SeoPath::normalize($data['sourcePath']) ?? '/';
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::ManageRedirects, scope: $scope, redirectSource: $source, redirectLocale: $locale));
        $redirectId = is_string($redirect) ? SeoModelIdentifier::uuid($redirect, SeoRedirect::class) : $redirect?->id;

        try {
            return SeoModelRegistry::newRedirect()->getConnection()->transaction(function () use (
                $actor,
                $data,
                $locale,
                $redirectId,
                $scope,
                $source,
            ): SeoRedirect {
                $this->lockGraph();
                $model = $redirectId === null
                    ? SeoModelRegistry::redirects()->withTrashed()
                        ->where('source_hash', SeoRedirect::sourceHash($scope, $locale, $source))
                        ->lockForUpdate()
                        ->first()
                    : SeoModelRegistry::redirects()->withTrashed()->lockForUpdate()->findOrFail($redirectId);
                if ($model instanceof SeoRedirect) {
                    $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::ManageRedirects, scope: $model->scope, redirectSource: $model->source_path, redirectLocale: $model->locale));
                }
                $wasDeleted = $model?->trashed() ?? false;
                $previousScope = $model?->scope;
                $previousSource = $model?->source_path;
                $model ??= SeoModelRegistry::newRedirect();

                if (is_int($data['expected_revision'] ?? null)
                    && (($model->exists && ! $wasDeleted
                        && $model->revision !== $data['expected_revision'])
                        || (! $model->exists && $data['expected_revision'] !== 0)
                        || ($wasDeleted && $data['expected_revision'] !== 0))) {
                    throw StaleSeoRedirectException::forRedirect(
                        $model->exists ? $model->id : 'new',
                    );
                }

                try {
                    $target = SeoRedirectTarget::normalize($data['target']);

                    if ($data['isActive']) {
                        $target = $this->chains->flatten(
                            $scope,
                            $locale,
                            $source,
                            $target,
                            $model->exists ? $model->id : null,
                        );
                    }
                } catch (InvalidArgumentException $exception) {
                    throw InvalidSeoMutationException::forField(
                        'target',
                        $exception->getMessage(),
                        $exception,
                    );
                }
                $attributes = [
                    'scope' => $scope,
                    'locale' => $locale,
                    'source_path' => $source,
                    'target' => $target,
                    'status_code' => $data['statusCode'],
                    'is_active' => $data['isActive'],
                    'expires_at' => $data['expiresAt'],
                ];

                if (array_key_exists('metadata', $data)) {
                    $attributes['metadata'] = $data['metadata'];
                }

                $model->fill($attributes);

                if ($wasDeleted) {
                    $model->restore();
                } else {
                    $model->save();
                }

                $this->chains->assertAcyclic($scope, $source);

                if ($previousScope !== null && $previousSource !== null
                    && ($previousScope !== $scope || $previousSource !== $source)) {
                    $this->chains->assertAcyclic($previousScope, $previousSource);
                }

                return $model->refresh();
            });
        } catch (QueryException $exception) {
            $redirectModel = SeoModelRegistry::newRedirect();
            $redirectLogicalTable = $redirectModel->getTable();
            $redirectTable = $redirectModel->getConnection()->getTablePrefix().$redirectLogicalTable;
            if (DatabaseConstraintViolation::matches($exception, [
                'seo_redirects_source_hash_unique',
                'seo_redirects_tenant_source_hash_unique',
                'seo_owned_'.substr(hash('sha256', $redirectLogicalTable.'source_hash'), 0, 24),
                'seo_redirects.source_hash',
                "{$redirectTable}.source_hash",
                "{$redirectTable}.owner_scope_key, {$redirectTable}.source_hash",
            ])) {
                throw StaleSeoRedirectException::forRedirect(
                    $redirectId ?? SeoRedirect::sourceHash($scope, $locale, $source),
                    $exception,
                );
            }

            throw $exception;
        }
    }

    public function find(Authenticatable|SystemActor $actor, string $source, ?string $scope = null, ?string $locale = null): ?SeoRedirect
    {
        $scope = SeoScope::normalize($scope);
        $locale = $locale === null ? null : $this->locales->assertSupported($locale);
        $source = SeoPath::normalize($source) ?? '/';
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor,
            ability: SeoAbility::ManageRedirects, scope: $scope,
            redirectSource: $source, redirectLocale: $locale,
        ));
        $redirect = SeoModelRegistry::redirects()
            ->where('source_hash', SeoRedirect::sourceHash($scope, $locale, $source))
            ->first();

        return $redirect;
    }

    public function prune(Authenticatable|SystemActor $actor, int $retentionDays = 30): int
    {
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::ManageRedirects));
        if ($retentionDays < 1) {
            throw new InvalidArgumentException('SEO redirect retention must be at least one day.');
        }

        if (OwnerSiteContext::current() !== null) {
            throw new OwnershipViolation('Public site admission cannot prune retained redirects.');
        }
        $connection = (SeoModelRegistry::newRedirect())->getConnection();
        $deletedBefore = now('UTC')->subDays($retentionDays);

        return $connection->transaction(
            static fn (): int => SeoModelRegistry::redirects()->onlyTrashed()
                ->where('deleted_at', '<=', $deletedBefore)
                ->toBase()->delete(),
        );
    }

    /** @return array{id:string,source:string,target:string,statusCode:int}|null */
    public function resolve(
        Authenticatable|SystemActor|PublicActor $actor,
        string $source,
        ?string $locale = null,
        ?string $scope = null,
    ): ?array {
        $this->authorization->authorize(new SeoAuthorizationContext(actor: $actor, ability: SeoAbility::Resolve, scope: $scope, redirectSource: $source, redirectLocale: $locale));
        if (! (bool) config('nvl-seo.redirects.enabled', true)) {
            return null;
        }

        $scope = SeoScope::normalize($scope);
        $locale = $locale === null ? null : $this->locales->assertSupported($locale);
        try {
            $source = SeoPath::normalize($source) ?? '/';
        } catch (InvalidArgumentException) {
            return null;
        }
        $redirect = $this->redirects->findActive($scope, $locale, $source);

        if (! $redirect instanceof SeoRedirect) {
            return null;
        }

        if ((bool) config('nvl-seo.redirects.record_hits', true)) {
            $redirectConnection = (SeoModelRegistry::newRedirect())->getConnection();
            SeoModelRegistry::redirects()->whereKey($redirect->id)->update([
                'hit_count' => $redirectConnection->raw('hit_count + 1'),
                'last_hit_at' => now(),
            ]);
        }

        return [
            'id' => $redirect->id,
            'source' => $redirect->source_path,
            'target' => $redirect->target,
            'statusCode' => $redirect->status_code,
        ];
    }
}
