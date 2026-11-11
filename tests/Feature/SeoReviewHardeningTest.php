<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Services\SeoRouteResolver;
use Nvl\Seo\Tests\Fixtures\CountingSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Seo\ValueObjects\StructuredDataNode;
use Nvl\Support\Enums\PublicActor;
use Nvl\Support\Enums\SystemActor;

it('resolves an incoming path that can never be stored as no redirect and no profile instead of failing', function (string $path): void {
    app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/old', 'target' => '/new']);

    expect(app(SeoRedirects::class)->resolve(PublicActor::Anonymous, $path))->toBeNull()
        ->and(app(SeoRouteResolver::class)->resolve($path, 'en'))->toBeNull()
        ->and(app(SeoRedirects::class)->resolve(PublicActor::Anonymous, '/old')['target'])->toBe('/new');
})->with([
    'encoded space' => ['/old%20page'],
    'dot segment' => ['/a/../old'],
    'encoded separator' => ['/a%2Fold'],
    'malformed escape' => ['/old%zz'],
    'query string' => ['/old?page=2'],
]);

it('treats a malformed profile or redirect identifier as not found', function (): void {
    $actor = SystemActor::Background;
    $owner = TestSeoOwner::query()->create(['name' => 'Article']);

    expect(fn () => app(SeoReader::class)->get($actor, 'not-a-uuid'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(SeoProfiles::class)->duplicate($actor, 'not-a-uuid', $owner))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(SeoProfiles::class)->archive($actor, 'not-a-uuid', true, 1))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(SeoProfiles::class)->delete($actor, 'not-a-uuid'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(SeoRedirects::class)->sync($actor, 'not-a-uuid', ['sourcePath' => '/old', 'target' => '/new']))->toThrow(ModelNotFoundException::class);
});

it('verifies unowned hosts once instead of re-normalising every registered host on each ownership check', function (): void {
    config()->set('nvl-seo.owners', ['counting' => CountingSeoOwner::class]);
    expect(SeoOwnership::declared())->toBeFalse();
    $constructed = CountingSeoOwner::$constructed;

    expect(SeoOwnership::declared())->toBeFalse()
        ->and(SeoOwnership::active())->toBeFalse()
        ->and(CountingSeoOwner::$constructed)->toBe($constructed)
        ->and(app(SeoOwnerRegistry::class)->modelClass('counting'))->toBe(CountingSeoOwner::class);

    config()->set('nvl-seo.owners', ['counting' => CountingSeoOwner::class, 'article' => TestSeoOwner::class]);

    expect(SeoOwnership::declared())->toBeFalse()
        ->and(CountingSeoOwner::$constructed)->toBeGreaterThan($constructed);
});

it('rejects structured data types and identifiers that end with a line break', function (): void {
    expect(fn () => new StructuredDataNode("Article\n"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new StructuredDataNode('Article', "#main\n"))->toThrow(InvalidArgumentException::class)
        ->and((new StructuredDataNode('Article', '#main'))->type)->toBe('Article');
});
