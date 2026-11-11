<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Support\Models\ConfiguredModelRegistration;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

/** Selects the two extensible SEO roots while retaining fixed child storage.
 * @api
 */
final class SeoModelRegistry
{
    /** @var ConfiguredModelRegistration<SeoProfile> */
    private readonly ConfiguredModelRegistration $profile;

    /** @var ConfiguredModelRegistration<SeoRedirect> */
    private readonly ConfiguredModelRegistration $redirect;

    /** @var array<class-string<Model>, true> */
    private array $storageValidated = [];

    public function __construct(Repository $configuration, OwnerRegistry $identities)
    {
        $this->profile = new ConfiguredModelRegistration($configuration, $identities, 'nvl-seo.models.profile', SeoProfile::class, 'seo.profiles', static function (string $class): void {
            if ($class::ownershipDefinition() != SeoProfile::ownershipDefinition()) {
                throw new OwnershipConfigurationInvalid('SEO profile replacements must retain the native ownership declaration.');
            }
        });
        $this->redirect = new ConfiguredModelRegistration($configuration, $identities, 'nvl-seo.models.redirect', SeoRedirect::class, 'seo.redirects', static function (string $class): void {
            if ($class::ownershipDefinition() != SeoRedirect::ownershipDefinition()) {
                throw new OwnershipConfigurationInvalid('SEO redirect replacements must retain the native ownership declaration.');
            }
        });
    }

    /**
     * Freeze both roots before native resource compilation; capabilities are validated on first use.
     *
     * @internal
     *
     * @return array{profile: class-string<SeoProfile>, redirect: class-string<SeoRedirect>}
     */
    public function prepare(): array
    {
        return ['profile' => $this->profile->prepare(), 'redirect' => $this->redirect->prepare()];
    }

    /** @return class-string<SeoProfile> */
    public static function profileClass(): string
    {
        $registry = Container::getInstance()->make(self::class);
        $class = $registry->profile->modelClass();
        $registry->assertStorage($class, SeoProfile::class);

        return $class;
    }

    /** @return class-string<SeoRedirect> */
    public static function redirectClass(): string
    {
        $registry = Container::getInstance()->make(self::class);
        $class = $registry->redirect->modelClass();
        $registry->assertStorage($class, SeoRedirect::class);

        return $class;
    }

    /** @internal
     * @param  array<string, mixed>  $attributes
     */
    public static function newProfile(array $attributes = []): SeoProfile
    {
        $class = self::profileClass();

        return new $class($attributes);
    }

    /** @internal */
    public static function newRedirect(): SeoRedirect
    {
        $class = self::redirectClass();

        return new $class;
    }

    /** @internal
     * @return Builder<SeoProfile>
     */
    public static function profiles(): Builder
    {
        return self::newProfile()->newQuery();
    }

    /** @internal
     * @return Builder<SeoRedirect>
     */
    public static function redirects(): Builder
    {
        return self::newRedirect()->newQuery();
    }

    /** Fixed translations and lock records must share the package-configured source.
     * @param  class-string<Model>  $class
     * @param  class-string<Model>  $baseClass
     */
    private function assertStorage(string $class, string $baseClass): void
    {
        if (isset($this->storageValidated[$class])) {
            return;
        }
        $model = new $class;
        $base = new $baseClass;
        if ($model->getTable() !== $base->getTable() || $model->getConnection() !== $base->getConnection()) {
            throw new OwnershipConfigurationInvalid('SEO replacement storage must use the package-configured table and connection.');
        }
        $this->storageValidated[$class] = true;
    }
}
