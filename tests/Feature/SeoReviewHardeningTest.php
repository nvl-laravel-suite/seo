<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Ownership\SeoOwnership;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Services\SeoRouteResolver;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Tests\Fixtures\CountingSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestIncrementsSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestIntegerSeoOwner;
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

it('rejects malformed registered owner keys before SQL', function (string $alias, string $id, bool $cleanup): void {
    $registry = app(SeoOwnerRegistry::class);
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect(fn () => $cleanup ? $registry->resolveForCleanup($alias, $id) : $registry->resolve($alias, $id))
            ->toThrow(ModelNotFoundException::class)
            ->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
})->with([
    'uuid owner' => ['article', 'not-a-uuid'],
    'integer owner text' => ['integer-article', 'not-an-integer'],
    'integer owner fraction' => ['integer-article', '1.5'],
    'integer owner overflow' => ['integer-article', '99999999999999999999'],
    'integer owner leading-zero expression' => ['integer-article', '0-1'],
])->with(['live' => [false], 'cleanup' => [true]]);

it('resolves well-formed registered owner keys after key validation', function (): void {
    $uuidOwner = TestSeoOwner::query()->create(['name' => 'Article']);
    $integerOwner = TestIntegerSeoOwner::query()->create(['name' => 'Integer']);
    $registry = app(SeoOwnerRegistry::class);

    expect($registry->resolve('article', $uuidOwner->getKey())->is($uuidOwner))->toBeTrue()
        ->and($registry->resolve('integer-article', '00'.$integerOwner->getKey())->is($integerOwner))->toBeTrue()
        ->and($registry->resolve('integer-article', $integerOwner->getKey())->is($integerOwner))->toBeTrue();
});

it('resolves a negative integer owner key written with leading zeros', function (): void {
    $owner = TestIntegerSeoOwner::query()->forceCreate(['id' => -5, 'name' => 'Negative']);
    $registry = app(SeoOwnerRegistry::class);

    expect($registry->resolve('integer-article', '-05')->is($owner))->toBeTrue()
        ->and(fn () => $registry->resolve('integer-article', '-06'))->toThrow(ModelNotFoundException::class);
});

it('treats an integer owner key wider than the owner key column as not found', function (): void {
    Schema::create('seo_test_increments_owners', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });
    config()->set('nvl-seo.owners', [...config('nvl-seo.owners'), 'increments-article' => TestIncrementsSeoOwner::class]);
    $owner = TestIncrementsSeoOwner::query()->create(['name' => 'Narrow']);
    $registry = app(SeoOwnerRegistry::class);

    expect(fn () => $registry->resolve('increments-article', '2147483648'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $registry->resolveForCleanup('increments-article', '-2147483649'))->toThrow(ModelNotFoundException::class)
        ->and($registry->resolve('increments-article', (string) $owner->getKey())->is($owner))->toBeTrue();
});

it('expires a redirect at the instant its offset expiry names', function (): void {
    CarbonImmutable::setTestNow('2026-11-30 12:00:00');
    $redirects = app(SeoRedirects::class);
    $redirects->sync(SystemActor::Background, null, [
        'sourcePath' => '/old', 'target' => '/new', 'expiresAt' => '2026-12-01T00:00:00-05:00',
    ]);

    CarbonImmutable::setTestNow('2026-12-01 04:59:00');
    expect($redirects->resolve(PublicActor::Anonymous, '/old')['target'] ?? null)->toBe('/new');

    CarbonImmutable::setTestNow('2026-12-01 05:00:01');
    expect($redirects->resolve(PublicActor::Anonymous, '/old'))->toBeNull();
});

it('rejects robots previews wider than their integer columns', function (string $field): void {
    $sync = static fn (int $value): mixed => app(SeoProfiles::class)
        ->sync(SystemActor::Background, TestSeoOwner::query()->create(['name' => 'Article']), [$field => $value]);

    expect(fn () => $sync(2147483648))->toThrow(fn (InvalidSeoMutationException $exception) => expect($exception->publicContext()['errors'])->toHaveKey($field))
        ->and($sync(2147483647)->exists)->toBeTrue();
})->with(['maxSnippet', 'maxVideoPreview']);

it('expires a redirect inside a repeated daylight-saving hour', function (): void {
    $timezone = date_default_timezone_get();
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    try {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01T04:00:00Z'));
        $redirects = app(SeoRedirects::class);
        $redirects->sync(SystemActor::Background, null, [
            'sourcePath' => '/old', 'target' => '/new', 'expiresAt' => '2026-11-01T01:30:00-04:00',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01T05:29:00Z'));
        $beforeExpiry = $redirects->resolve(PublicActor::Anonymous, '/old')['target'] ?? null;
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01T06:15:00Z'));
        $repeatedHour = $redirects->resolve(PublicActor::Anonymous, '/old');

        expect($beforeExpiry)->toBe('/new')
            ->and($repeatedHour)->toBeNull()
            ->and(SeoRedirect::query()->sole()->expires_at?->getTimestamp())->toBe(CarbonImmutable::parse('2026-11-01T05:30:00Z')->getTimestamp());
    } finally {
        date_default_timezone_set($timezone);
    }
});

it('authorizes the canonical scope and path that the read then uses', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Private article']);
    app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['title' => 'Private']]], 'private');
    app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/secret', 'target' => '/hidden'], 'private');
    app()->instance(SeoAuthorization::class, new class implements SeoAuthorization
    {
        public function authorize(SeoAuthorizationContext $context): void
        {
            if ($context->scope === 'private' || $context->redirectSource === '/secret') {
                throw new AuthorizationException('Denied.');
            }
        }
    });

    expect(fn () => app(SeoReader::class)->resolve(SystemActor::Background, $owner, 'en', ' PRIVATE '))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SeoRedirects::class)->resolve(SystemActor::Background, '/secret/', null, 'Private'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SeoRedirects::class)->resolve(SystemActor::Background, '//secret', null, 'other'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SeoProfiles::class)->status(SystemActor::Background, 'Private'))->toThrow(AuthorizationException::class);
});

it('rejects SEO text the database would truncate or refuse instead of storing part of it', function (string $field, string $text): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Article']);

    expect(fn () => app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => [$field => $text]]]))
        ->toThrow(InvalidSeoMutationException::class)
        ->and(SeoProfile::query()->count())->toBe(0);
})->with(['title', 'description', 'robots', 'keywords', 'author', 'imageReference', 'imageAlt', 'openGraphTitle', 'openGraphDescription', 'twitterTitle', 'twitterDescription'])
    ->with(['NUL byte' => ["a\0b"], 'invalid UTF-8' => ["a\xFFb"]]);

it('rejects redirect and route paths that are not valid UTF-8 instead of failing in the database', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Article']);
    $redirect = static fn (string $source, string $target): mixed => app(SeoRedirects::class)
        ->sync(SystemActor::Background, null, ['sourcePath' => $source, 'target' => $target]);

    expect(fn () => $redirect("/old\xFF", '/new'))->toThrow(InvalidSeoMutationException::class)
        ->and(fn () => $redirect('/old', "/new\xFF"))->toThrow(InvalidSeoMutationException::class)
        ->and(fn () => $redirect('/old', "https://example.test/new\xFF"))->toThrow(InvalidSeoMutationException::class)
        ->and(fn () => app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => "/page\xFF"]]]))->toThrow(InvalidSeoMutationException::class)
        ->and(app(SeoRedirects::class)->resolve(PublicActor::Anonymous, "/old\xFF"))->toBeNull()
        ->and(SeoRedirect::query()->count())->toBe(0);
});
