<?php

declare(strict_types=1);

use Nvl\Ownership\Contracts\OwnerSiteResolver;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Seo\Support\SeoScope;
use Nvl\Seo\Tests\Fixtures\OwnershipCompany;
use Nvl\Seo\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Seo\Tests\Fixtures\UnsafeOwnedSitemap;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\ValueObjects\VerifiedOwnerSiteContext;

beforeEach(function (): void {
    config(['nvl-seo.routes.enabled' => true]);
    $this->app->bind(OwnerSiteResolver::class, static fn () => new class implements OwnerSiteResolver
    {
        public function resolve(string $origin): ?VerifiedOwnerSiteContext
        {
            if (! in_array(parse_url($origin, PHP_URL_HOST), ['a.seo.test', 'b.seo.test'], true)) {
                return null;
            }

            return new VerifiedOwnerSiteContext(
                app(OwnerIdentityResolver::class)->reference(OwnershipCompany::findOrFail(parse_url($origin, PHP_URL_HOST) === 'a.seo.test' ? 7 : 8)),
                'default', 'https://'.parse_url($origin, PHP_URL_HOST), ['fixture.content-owners', 'seo.profiles', 'seo.translations', 'seo.redirects']);
        }
    });
    foreach ([7, 8] as $id) {
        $this->owned($id, function () use ($id): void {
            $owner = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, fn (array $attributes) => OwnershipContentOwner::create([...$attributes, 'name' => 'Subject '.$id]));
            app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => '/owner-'.$id, 'title' => 'Owner '.$id]]]);
        });
    }
});

it('keeps public sitemap artifacts and canonical origins owner local', function (): void {
    $keys = [];
    foreach (['a' => 7, 'b' => 8] as $host => $id) {
        app(PublicOwnerSiteRunner::class)->run('https://'.$host.'.seo.test', function () use ($host, $id, &$keys): void {
            $xml = app(SitemapGenerator::class)->generate();
            expect($xml)->toContain('https://'.$host.'.seo.test/owner-'.$id)->not->toContain('/owner-'.($id === 7 ? 8 : 7));
            $keys[] = app(SitemapCache::class)->key('default');
            expect(fn () => SeoScope::normalize('foreign'))->toThrow(InvalidSeoMutationException::class);
        });
    }
    expect($keys[0])->not->toBe($keys[1]);
});

it('invalidates an existing public artifact when management commits a profile mutation', function (): void {
    $origin = 'https://a.seo.test';
    $before = app(PublicOwnerSiteRunner::class)->run($origin, fn () => app(SitemapCache::class)->key('default'));
    $this->owned(7, function (): void {
        $profile = SeoProfile::firstOrFail();
        app(SeoProfiles::class)->sync(SystemActor::Background, $profile->seoable, ['translations' => ['en' => ['title' => 'Changed']]]);
    });
    $after = app(PublicOwnerSiteRunner::class)->run($origin, fn () => app(SitemapCache::class)->key('default'));
    expect($after)->not->toBe($before);
});

it('rejects a sitemap extension that lacks an ownership capability before executing it', function (): void {
    app(SitemapRegistry::class)->registerType(UnsafeOwnedSitemap::class, 'unsafe');
    app(PublicOwnerSiteRunner::class)->run('https://a.seo.test', function (): void {
        expect(fn () => app(SitemapGenerator::class)->generate())->toThrow(InvalidArgumentException::class);
    });
});

it('warms a real sitemap through the same verified origin at the scheduled command boundary', function (): void {
    $this->artisan('nvl:seo:sitemap:warm', ['--origin' => 'https://a.seo.test'])->assertSuccessful();
    $this->artisan('nvl:seo:sitemap:warm')->assertExitCode(2);
});

it('revalidates reduced public resource capabilities before serving a warm sitemap', function (): void {
    $origin = 'https://a.seo.test';
    $runner = app(PublicOwnerSiteRunner::class);
    $runner->run($origin, fn () => app(SitemapGenerator::class)->generate());
    $original = app(OwnerSiteResolver::class);
    $this->app->bind(OwnerSiteResolver::class, static fn () => new class($original) implements OwnerSiteResolver
    {
        public function __construct(private OwnerSiteResolver $original) {}

        public function resolve(string $origin): ?VerifiedOwnerSiteContext
        {
            $site = $this->original->resolve($origin);

            return $site === null ? null : new VerifiedOwnerSiteContext($site->owner, $site->site, $site->canonicalOrigin, array_values(array_diff($site->resources, ['seo.translations'])));
        }
    });
    expect(fn () => $runner->run($origin, fn () => app(SitemapGenerator::class)->generate()))
        ->toThrow(OwnershipViolation::class);
});

it('does not grant redirect maintenance through public site admission', function (): void {
    expect(fn () => app(PublicOwnerSiteRunner::class)->run('https://a.seo.test', fn () => app(SeoRedirects::class)->prune(SystemActor::Background)))
        ->toThrow(OwnershipViolation::class);
});
