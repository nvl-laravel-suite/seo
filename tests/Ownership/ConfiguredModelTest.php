<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Ownership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Tests\Fixtures\CustomSeoProfile;
use Nvl\Seo\Tests\Fixtures\CustomSeoRedirect;
use Nvl\Seo\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;

trait ConfiguresOwnedSeoModelEnvironment
{
    /** @var array<string, class-string<Model>>|null */
    protected ?array $previousMorphMap = null;

    protected function restoreConfiguredMorphMap(): void
    {
        Relation::morphMap($this->previousMorphMap ?? [], false);
    }

    protected function defineEnvironment($app): void
    {
        $this->previousMorphMap ??= Relation::morphMap();
        Relation::morphMap([], false);
        parent::defineEnvironment($app);
        $app['config']->set('nvl-seo.models.profile', CustomSeoProfile::class);
        $app['config']->set('nvl-seo.models.redirect', CustomSeoRedirect::class);
    }
}

uses(ConfiguresOwnedSeoModelEnvironment::class);

afterEach(function (): void {
    $this->restoreConfiguredMorphMap();
});

it('selected roots and fixed child graph survive native adoption and owner isolation', function (): void {
    $this->assertSame(CustomSeoProfile::class, app(OwnershipResourceRegistry::class)->get('seo.profiles')->model);
    $this->assertSame(CustomSeoRedirect::class, app(OwnershipResourceRegistry::class)->get('seo.redirects')->model);
    foreach ([7, 8] as $id) {
        $this->owned($id, function () use ($id): void {
            $owner = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, fn (array $attributes) => OwnershipContentOwner::create([...$attributes, 'name' => 'Owner '.$id]));
            $profile = app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => '/same', 'title' => 'Owner '.$id]]]);
            $redirect = app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/old', 'target' => '/new-'.$id]);
            $this->assertSame(CustomSeoProfile::class, $profile::class);
            $this->assertSame(CustomSeoProfile::class, $profile->translations->first()->profile::class);
            $this->assertSame(CustomSeoRedirect::class, $redirect::class);
            $this->assertStringStartsWith('Owner '.$id, app(SeoReader::class)->resolve(SystemActor::Background, $profile, 'en')['title']);
            $this->assertSame(1, CustomSeoProfile::query()->count());
            $this->assertSame(1, CustomSeoRedirect::query()->count());
        });
    }
});
