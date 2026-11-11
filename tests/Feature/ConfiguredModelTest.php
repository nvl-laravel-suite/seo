<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Services\SeoModelRegistry;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Services\SeoRouteResolver;
use Nvl\Seo\Tests\Fixtures\CustomSeoProfile;
use Nvl\Seo\Tests\Fixtures\CustomSeoRedirect;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;
use RuntimeException;

trait ConfiguresSeoModelEnvironment
{
    /** @var array<string, class-string<Model>>|null */
    protected ?array $previousMorphMap = null;

    protected function restoreConfiguredMorphMap(): void
    {
        Relation::morphMap($this->previousMorphMap ?? [], false);
    }

    /** @var class-string */
    protected string $profileModel = CustomSeoProfile::class;

    /** @var class-string */
    protected string $redirectModel = CustomSeoRedirect::class;

    protected function defineEnvironment($app): void
    {
        $this->previousMorphMap ??= Relation::morphMap();
        Relation::morphMap([], false);
        parent::defineEnvironment($app);
        $app['config']->set('nvl-seo.models.profile', $this->profileModel);
        $app['config']->set('nvl-seo.models.redirect', $this->redirectModel);
    }
}

uses(ConfiguresSeoModelEnvironment::class);

afterEach(function (): void {
    $this->restoreConfiguredMorphMap();
});

it('profiles reads child relations factories and morph identity use the selected model', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Article']);
    $profile = app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => '/article', 'title' => 'Article']]]);
    $this->assertSame(CustomSeoProfile::class, $profile::class);
    $this->assertSame(CustomSeoProfile::class, $owner->seoProfiles()->first()::class);
    $this->assertSame(CustomSeoProfile::class, $profile->translations->first()->profile::class);
    $this->assertSame(CustomSeoProfile::class, app(SeoRouteResolver::class)->resolve('/article', 'en')::class);
    $this->assertSame('Article | Example', app(SeoReader::class)->resolve(SystemActor::Background, $profile, 'en')['title']);
    $this->assertSame(CustomSeoProfile::class, SeoProfile::factory()->forOwner(TestSeoOwner::query()->create(['name' => 'Factory']))->create()::class);
    $this->assertSame(CustomSeoProfile::class, Relation::getMorphedModel($profile->getMorphClass()));
    $this->assertSame(CustomSeoProfile::class, SeoProfileTranslation::factory()->forProfile($profile)->state(['locale' => 'bg'])->create()->profile::class);
});

it('redirect sync reads factories and morph identity use the selected model', function (): void {
    $redirect = app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/old', 'target' => '/new']);
    $this->assertSame(CustomSeoRedirect::class, $redirect::class);
    $this->assertSame(CustomSeoRedirect::class, app(SeoRedirects::class)->find(SystemActor::Background, '/old')::class);
    $this->assertSame(CustomSeoRedirect::class, SeoRedirect::factory()->create()::class);
    $this->assertSame(CustomSeoRedirect::class, Relation::getMorphedModel($redirect->getMorphClass()));
});

it('profile translation and redirect writes roll back on their actual source', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Article']);
    try {
        $owner->getConnection()->transaction(function () use ($owner): void {
            app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => '/rollback']]]);
            app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/old', 'target' => '/new']);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        $this->assertSame('rollback', $exception->getMessage());
    }
    $this->assertSame(0, CustomSeoProfile::query()->count());
    $this->assertSame(0, SeoProfileTranslation::query()->count());
    $this->assertSame(0, CustomSeoRedirect::query()->count());
});

it('profile replacement cannot leave fixed translation storage', function (): void {
    $this->profileModel = ForeignProfileConnection::class;
    $this->expectException(OwnershipConfigurationInvalid::class);
    $this->expectExceptionMessage('package-configured table and connection');
    $morphMap = Relation::morphMap();
    Relation::morphMap([], false);
    try {
        $this->refreshApplication();
        SeoModelRegistry::profileClass();
    } finally {
        Relation::morphMap($morphMap, false);
    }
});

it('redirect replacement must preserve required casts on first use of the model registry', function (): void {
    $this->redirectModel = UnsafeRedirectCasts::class;
    $this->expectException(OwnershipConfigurationInvalid::class);
    $this->expectExceptionMessage('required package casts');
    $morphMap = Relation::morphMap();
    Relation::morphMap([], false);
    try {
        $this->refreshApplication();
        expect(app()->isBooted())->toBeTrue();
        SeoModelRegistry::redirects();
    } finally {
        Relation::morphMap($morphMap, false);
    }
});

final class ForeignProfileConnection extends SeoProfile
{
    public function getTable(): string
    {
        return 'foreign_profiles';
    }
}

final class UnsafeRedirectCasts extends SeoRedirect
{
    protected function casts(): array
    {
        return [...parent::casts(), 'is_active' => 'string'];
    }
}
