<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\ApcStore;
use Illuminate\Cache\ApcWrapper;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Contracts\SeoImportSource;
use Nvl\Seo\Contracts\SitemapArtifactStore;
use Nvl\Seo\Contracts\SitemapSource;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Events\SeoProfileChanged;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Exceptions\SeoRedirectLoopException;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Exceptions\StaleSeoRedirectException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Services\AbsoluteUrl;
use Nvl\Seo\Services\RobotsGenerator;
use Nvl\Seo\Services\SeoDoctor;
use Nvl\Seo\Services\SeoHeadRenderer;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Services\SeoSchemaAdopter;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Seo\Support\SeoAuthorizationContext;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Tests\Fixtures\OwnershipActor;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Seo\ValueObjects\SitemapEntry;
use Nvl\Support\Adoption\AdoptionAdapter;
use Nvl\Support\Adoption\AdoptionCoordinator;
use Nvl\Support\Adoption\AdoptionException;
use Nvl\Support\Adoption\AdoptionRegistry;
use Nvl\Support\Adoption\AdoptionStep;
use Nvl\Support\Adoption\AdoptionStore;
use Nvl\Support\Enums\SystemActor;
use Nvl\Translatable\Enums\TranslationSyncMode;

function createLegacySeoSchema(): void
{
    Schema::create('seo', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuidMorphs('seoable');
        $table->timestamps();
    });
    Schema::create('seo_i18n', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('seo_id')->constrained('seo');
        $table->char('locale', 2);
        foreach (['path', 'title', 'canonical_url', 'author', 'image_url', 'robots'] as $field) {
            $table->string($field)->nullable();
        }
        $table->text('description')->nullable();
        $table->text('keywords')->nullable();
        $table->json('additional_data')->nullable();
        $table->json('jsonld_data')->nullable();
        $table->boolean('prepend_site_title')->default(true);
        $table->unique(['seo_id', 'locale']);
        $table->timestamps();
    });
}

/** Load the shared core adoption run and checkpoint migration on the default connection. */
function seoAdoptionMigration(): Migration
{
    $directory = dirname((string) (new ReflectionClass(AdoptionStore::class))->getFileName(), 3).'/database/migrations';
    $migration = require (glob($directory.'/*_nvl_core_create_adoption_tables.php') ?: [''])[0];

    return $migration instanceof Migration ? $migration : throw new RuntimeException('The core adoption migration is missing.');
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function seoAdoptCommand(array $arguments): array
{
    $status = Artisan::call('nvl:adopt', ['package' => 'seo', ...$arguments, '--format' => 'json']);

    return ['status' => $status, ...json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

it('honors every accepted revision precondition', function (): void {
    $actor = SystemActor::Background;
    $owner = TestSeoOwner::query()->create(['name' => 'Revisioned']);
    $profiles = app(SeoProfiles::class);
    $redirects = app(SeoRedirects::class);

    expect(fn () => $profiles->sync($actor, $owner, ['expected_revision' => '1', 'translations' => ['en' => ['title' => 'Absent']]]))
        ->toThrow(StaleSeoProfileException::class);
    $profile = $profiles->sync($actor, $owner, ['expected_revision' => '0', 'translations' => ['en' => ['title' => 'Created']]]);
    $profile = $profiles->sync($actor, $owner, ['expected_revision' => (string) $profile->revision, 'translations' => ['en' => ['title' => 'Fresh']]]);
    expect(fn () => $profiles->sync($actor, $owner, ['expected_revision' => (string) ($profile->revision - 1), 'translations' => ['en' => ['title' => 'Stale']]]))
        ->toThrow(StaleSeoProfileException::class)
        ->and($profile->refresh()->getTranslation('en')?->title)->toBe('Fresh');

    expect(fn () => $redirects->sync($actor, null, ['sourcePath' => '/old', 'target' => '/new', 'expected_revision' => '1']))
        ->toThrow(StaleSeoRedirectException::class);
    $redirect = $redirects->sync($actor, null, ['sourcePath' => '/old', 'target' => '/new', 'expected_revision' => '0']);
    $redirect = $redirects->sync($actor, $redirect, ['sourcePath' => '/old', 'target' => '/newer', 'expected_revision' => (string) $redirect->revision]);
    expect(fn () => $redirects->sync($actor, $redirect, ['sourcePath' => '/old', 'target' => '/stale', 'expected_revision' => (string) ($redirect->revision - 1)]))
        ->toThrow(StaleSeoRedirectException::class)
        ->and($redirect->refresh()->target)->toBe('/newer');
});

it('authorizes redirect updates against the stored source identity', function (string $identity): void {
    $redirect = app(SeoRedirects::class)->sync(SystemActor::Background, null, [
        'sourcePath' => '/protected', 'target' => '/original', 'locale' => 'bg',
    ], 'protected');
    app()->instance(SeoAuthorization::class, new class implements SeoAuthorization
    {
        public function authorize(SeoAuthorizationContext $context): void
        {
            if ($context->scope === 'protected' || $context->redirectSource === '/protected' || $context->redirectLocale === 'bg') {
                throw new AuthorizationException;
            }
        }
    });

    expect(fn () => app(SeoRedirects::class)->sync(SystemActor::Background, $identity === 'model' ? $redirect : $redirect->id, [
        'sourcePath' => '/allowed', 'target' => '/replacement', 'locale' => 'en', 'expected_revision' => 1,
    ], 'allowed'))->toThrow(AuthorizationException::class);

    expect($redirect->fresh()?->source_path)->toBe('/protected')
        ->and($redirect->fresh()?->scope)->toBe('protected')
        ->and($redirect->fresh()?->revision)->toBe(1);
})->with(['model', 'id']);

it('supplies current persisted actor facts to SEO host authorization', function (string $mutation): void {
    Schema::create('content_owned_actors', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    $actor = OwnershipActor::query()->create(['name' => $mutation === 'revoked' ? 'Allowed' : 'Denied']);
    if ($mutation === 'attribute') {
        $actor->name = 'Allowed';
    } elseif ($mutation === 'relation') {
        $actor->setRelation('grant', new OwnershipActor);
    } else {
        OwnershipActor::query()->whereKey($actor->getKey())->update(['name' => 'Denied']);
    }
    app()->instance(SeoAuthorization::class, new class implements SeoAuthorization
    {
        public function authorize(SeoAuthorizationContext $context): void
        {
            if (! $context->actor instanceof OwnershipActor || ($context->actor->name !== 'Allowed' && ! $context->actor->relationLoaded('grant'))) {
                throw new AuthorizationException;
            }
        }
    });

    expect(fn () => app(SeoProfiles::class)->status($actor))->toThrow(AuthorizationException::class);
})->with(['attribute', 'relation', 'revoked']);

it('enforces mutation invariants for programmatic action callers', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Invalid profile owner']);

    expect(fn () => app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => [
                    'path' => '/unsafe/%252fsegment',
                    'canonicalUrl' => 'https://example.test/article#fragment',
                ],
            ],
        ],
    ))->toThrow(InvalidSeoMutationException::class)
        ->and(fn () => app(SeoRedirects::class)->sync(SystemActor::Background,
            null,
            [
                'sourcePath' => '/legacy',
                'target' => '/current',
                'statusCode' => 305,
            ],
        ))->toThrow(InvalidSeoMutationException::class)
        ->and(fn () => app(SeoRedirects::class)->sync(SystemActor::Background,
            null,
            [
                'sourcePath' => '/legacy-absolute',
                'target' => 'https://example.test/unsafe/%2fsegment',
            ],
        ))->toThrow(InvalidSeoMutationException::class)
        ->and(SeoProfile::query()->count())->toBe(0)
        ->and(SeoRedirect::query()->count())->toBe(0);
});

it('canonicalizes unreserved path encoding and rejects malformed encoding', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Encoded path owner']);
    $profile = app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => ['path' => '/articles/%41udit%7epass'],
            ],
        ],
    );

    expect($profile->getTranslation('en')?->getAttribute('path'))
        ->toBe('/articles/Audit~pass')
        ->and(fn () => app(SeoProfiles::class)->sync(SystemActor::Background,
            TestSeoOwner::query()->create(['name' => 'Malformed encoded path']),
            [
                'translations' => [
                    'en' => ['path' => '/articles/%invalid'],
                ],
            ],
        ))
        ->toThrow(InvalidSeoMutationException::class);
});

it('rejects unknown programmatic translation fields without persisting a profile', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Unknown field owner']);

    expect(fn () => app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => [
                    'path' => '/unknown-field',
                    'titel' => 'Misspelled title',
                ],
            ],
        ],
    ))
        ->toThrow(InvalidSeoMutationException::class)
        ->and(SeoProfile::query()->count())->toBe(0);
});

it('rejects crawler identity urls with fragments or foreign origins', function (): void {
    expect(fn () => new SitemapEntry('https://example.test/page#section'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SitemapEntry(
            'https://example.test/page',
            priority: '1e-1',
        ))
        ->toThrow(InvalidArgumentException::class, 'decimal notation');

    config()->set('nvl-seo.sitemap.cache_seconds', 0);
    app(SitemapRegistry::class)->register(
        new class implements SitemapSource
        {
            public function entries(string $scope): iterable
            {
                yield new SitemapEntry('https://foreign.example.test/page');
            }
        },
        'test.foreign-origin',
    );

    expect(fn () => app(SitemapGenerator::class)->generate())
        ->toThrow(LogicException::class, 'does not share the sitemap origin');
});

it('rejects base urls containing query strings or fragments', function (): void {
    config()->set('nvl-seo.site.base_url', 'https://example.test/base?tenant=one');

    expect(app(AbsoluteUrl::class)->resolve('/page'))->toBeNull();

    config()->set('nvl-seo.site.base_url', 'https://example.test/base#content');

    expect(app(AbsoluteUrl::class)->resolve('/page'))->toBeNull();
});

it('isolates sitemap cache identities by configured site url', function (): void {
    $cache = app(SitemapCache::class);
    $firstSiteKey = $cache->key('default');
    config()->set('nvl-seo.site.base_url', 'https://second.example.test');

    expect($cache->key('default'))->not->toBe($firstSiteKey);
});

it('enforces sitemap path scope for same-origin source entries', function (): void {
    config()->set([
        'nvl-seo.routes.sitemap_path' => 'maps/sitemap.xml',
        'nvl-seo.routes.sitemap_chunk_path' => 'maps/sitemap-{chunk}.xml',
        'nvl-seo.sitemap.cache_seconds' => 0,
    ]);
    app(SitemapRegistry::class)->register(
        new class implements SitemapSource
        {
            public function entries(string $scope): iterable
            {
                yield new SitemapEntry('https://example.test/outside');
            }
        },
        'test.outside-path-scope',
    );

    expect(fn () => app(SitemapGenerator::class)->generate())
        ->toThrow(LogicException::class, 'outside sitemap path scope');
});

it('requires atomic lock support before generating cached sitemaps', function (): void {
    $this->commitDatabaseFixture();
    $cache = new CacheRepository(new ApcStore(new class extends ApcWrapper
    {
        /**
         * Simulate an empty APC cache without requiring the extension.
         *
         * @param  string  $key
         */
        public function get($key): mixed
        {
            return null;
        }
    }));
    $artifacts = app(SitemapArtifactStore::class);
    $generator = new SitemapGenerator(
        app(SitemapRegistry::class),
        new SitemapCache($cache, $artifacts),
        $cache,
        $artifacts,
        app(AbsoluteUrl::class),
    );

    expect(fn () => $generator->generate())
        ->toThrow(LogicException::class, 'atomic lock support');
});

it('self heals cached sitemap manifests whose published artifact disappeared', function (): void {
    $this->commitDatabaseFixture();
    Storage::fake('seo-healing');
    config()->set('nvl-seo.sitemap.disk', 'seo-healing');
    $cache = app(SitemapCache::class);
    $generator = app(SitemapGenerator::class);
    $originalNamespace = $cache->namespace('default');
    $originalArtifact = "nvl-seo/sitemaps/{$originalNamespace}/chunk-1.xml";

    $generator->generate();
    Storage::disk('seo-healing')->delete($originalArtifact);

    expect(Storage::disk('seo-healing')->exists($originalArtifact))->toBeFalse();

    $xml = $generator->generate();
    $recoveredNamespace = $cache->namespace('default');
    $recoveredArtifact = "nvl-seo/sitemaps/{$recoveredNamespace}/chunk-1.xml";

    expect($xml)->toContain('<urlset')
        ->and($recoveredNamespace)->not->toBe($originalNamespace)
        ->and(Storage::disk('seo-healing')->exists($recoveredArtifact))->toBeTrue();
});

it('keeps transactional sitemap reads out of shared artifacts on the actual profile connection', function (string $operation): void {
    $this->commitDatabaseFixture();
    Storage::fake('seo-transaction');
    config()->set('nvl-seo.sitemap.disk', 'seo-transaction');
    $owner = TestSeoOwner::query()->create(['name' => 'Uncommitted sitemap owner']);
    $connection = (new SeoProfile)->getConnection();
    $generator = app(SitemapGenerator::class);
    $connection->beginTransaction();

    try {
        app(SeoProfiles::class)->sync(SystemActor::Background, $owner, [
            'translations' => ['en' => ['path' => '/rolled-back-profile']],
        ]);
        config()->set('nvl-seo.connection', $connection->getName());
        config()->set('database.connections.seo_unrelated_default', $connection->getConfig());
        DB::setDefaultConnection('seo_unrelated_default');
        expect(DB::connection()->transactionLevel())->toBe(0);

        $result = match ($operation) {
            'generate' => $generator->generate(),
            'generateChunk' => $generator->generateChunk(1),
            'chunkCount' => $generator->chunkCount(),
        };
        if (is_string($result)) {
            expect($result)->toContain('/rolled-back-profile');
        } else {
            expect($result)->toBe(1);
        }
        expect(Storage::disk('seo-transaction')->allFiles())->toBe([]);
    } finally {
        $connection->rollBack();
        DB::setDefaultConnection($connection->getName());
    }

    expect(SeoProfile::query()->count())->toBe(0)
        ->and($generator->generate())->not->toContain('/rolled-back-profile')
        ->and(Storage::disk('seo-transaction')->allFiles())->not->toBe([]);
})->with(['generate', 'generateChunk', 'chunkCount']);

it('preserves committed sitemap artifacts while reading a changed profile transaction', function (): void {
    $this->commitDatabaseFixture();
    Storage::fake('seo-transaction');
    config()->set('nvl-seo.sitemap.disk', 'seo-transaction');
    $owner = TestSeoOwner::query()->create(['name' => 'Committed sitemap owner']);
    $profiles = app(SeoProfiles::class);
    $profile = $profiles->sync(SystemActor::Background, $owner, [
        'translations' => ['en' => ['path' => '/committed-profile']],
    ]);
    $generator = app(SitemapGenerator::class);
    $committed = $generator->generate();
    $artifact = Storage::disk('seo-transaction')->allFiles()[0];
    $connection = $profile->getConnection();
    $connection->beginTransaction();

    try {
        $profiles->sync(SystemActor::Background, $owner, [
            'expected_revision' => $profile->revision,
            'translations' => ['en' => ['path' => '/rolled-back-change']],
        ]);
        expect($generator->generate())->toContain('/rolled-back-change')->not->toContain('/committed-profile')
            ->and(Storage::disk('seo-transaction')->get($artifact))->toBe($committed);
    } finally {
        $connection->rollBack();
    }

    expect($generator->generate())->toBe($committed);
});

it('keeps a registered source transaction out of shared sitemap artifacts', function (string $operation): void {
    $this->commitDatabaseFixture();
    Storage::fake('seo-source-transaction');
    config()->set('nvl-seo.sitemap.disk', 'seo-source-transaction');
    $profileConnection = (new SeoProfile)->getConnection();
    config()->set('database.connections.seo_custom_source', $profileConnection->getConfig());
    $source = DB::connection('seo_custom_source');
    $source->getSchemaBuilder()->create('seo_custom_sitemap_entries', static function (Blueprint $table): void {
        $table->string('url');
    });
    app(SitemapRegistry::class)->register(new class($source) implements SitemapSource
    {
        public function __construct(private Connection $connection) {}

        public function entries(string $scope): iterable
        {
            foreach ($this->connection->table('seo_custom_sitemap_entries')->pluck('url') as $url) {
                yield new SitemapEntry($url);
            }
        }
    }, 'test.transactional-source');
    $generator = app(SitemapGenerator::class);
    $source->beginTransaction();

    try {
        $source->table('seo_custom_sitemap_entries')->insert(['url' => 'https://example.test/rolled-back-source']);
        expect($profileConnection->transactionLevel())->toBe(0);
        $result = match ($operation) {
            'generate' => $generator->generate(),
            'generateChunk' => $generator->generateChunk(1),
            'chunkCount' => $generator->chunkCount(),
        };
        if (is_string($result)) {
            expect($result)->toContain('/rolled-back-source');
        } else {
            expect($result)->toBe(1);
        }
        expect(Storage::disk('seo-source-transaction')->allFiles())->toBe([]);
    } finally {
        $source->rollBack();
    }

    expect($generator->generate())->not->toContain('/rolled-back-source')
        ->and(Storage::disk('seo-source-transaction')->allFiles())->not->toBe([]);
})->with(['generate', 'generateChunk', 'chunkCount']);

it('keeps committed profile writes successful when sitemap invalidation fails', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Cache failure owner']);
    $cacheRepository = new CacheRepository(new class extends ArrayStore
    {
        public function increment($key, $value = 1): bool
        {
            return false;
        }
    });
    $cache = new SitemapCache(
        $cacheRepository,
        app(SitemapArtifactStore::class),
    );
    app()->instance(SitemapCache::class, $cache);
    Exceptions::fake();
    Event::fake([SeoProfileChanged::class]);

    $profile = app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => [
                    'path' => '/cache-failure',
                    'title' => 'Cache failure',
                ],
            ],
        ],
    );

    expect($profile->exists)->toBeTrue()
        ->and(SeoProfile::query()->whereKey($profile->id)->exists())->toBeTrue();
    Event::assertDispatched(SeoProfileChanged::class);
    Exceptions::assertReported(LogicException::class);

    $this->artisan('nvl:seo:sitemap:clear')
        ->assertFailed();
});

it('publishes immutable localized path snapshots only after the profile connection commits', function (): void {
    $this->commitDatabaseFixture();
    $owner = TestSeoOwner::query()->create(['name' => 'Path snapshots']);
    $connection = (new SeoProfile)->getConnection();
    $profiles = app(SeoProfiles::class);
    $actor = SystemActor::Background;
    Event::fake([SeoProfileChanged::class]);

    try {
        $profile = $connection->transaction(function () use ($profiles, $actor, $owner): SeoProfile {
            $profile = $profiles->sync($actor, $owner, ['translations' => ['en' => ['path' => '/snapshot-old'], 'bg' => ['path' => '/snapshot-bg']]]);
            Event::assertNotDispatched(SeoProfileChanged::class);

            return $profile;
        });
        $first = Event::dispatched(SeoProfileChanged::class)->sole()[0];
        expect($first->schemaVersion())->toBe(2)
            ->and($first->ownerType)->toBe($owner->getMorphClass())->and($first->ownerId)->toBe($owner->getKey())
            ->and($first->previousTranslations)->toBe([])
            ->and(array_column($first->currentTranslations, 'path'))->toBe(['/snapshot-bg', '/snapshot-old']);

        $connection->beginTransaction();
        try {
            $profiles->sync($actor, $owner, ['translations' => ['en' => ['path' => '/snapshot-rollback']]]);
        } finally {
            $connection->rollBack();
        }
        Event::assertDispatchedTimes(SeoProfileChanged::class, 1);

        $profile = $profiles->sync($actor, $owner, ['translations' => ['en' => ['path' => '/snapshot-new']]], translationMode: TranslationSyncMode::Replace);
        $second = Event::dispatched(SeoProfileChanged::class)->last()[0];
        expect($second->previousTranslations)->toBe($first->currentTranslations)
            ->and($second->currentTranslations)->toBe(['en' => ['id' => $profile->translations->sole()->id, 'path' => '/snapshot-new']]);

        expect($profiles->delete($actor, $profile))->toBeTrue();
        $deleted = Event::dispatched(SeoProfileChanged::class)->last()[0];
        expect($deleted->operation)->toBe('deleted')->and($deleted->previousTranslations)->toBe($second->currentTranslations)
            ->and($deleted->currentTranslations)->toBe([]);
    } finally {
        SeoProfile::query()->where('seoable_id', $owner->getKey())->delete();
        $owner->forceDelete();
    }
});

it('renders localized robots and head metadata and inherits missing locale overrides', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Localized metadata']);
    $actor = SystemActor::Background;
    $profiles = app(SeoProfiles::class);
    $profiles->sync($actor, $owner, ['translations' => [
        'en' => ['title' => 'Landing', 'robots' => 'noindex, nofollow', 'prependSiteTitle' => true, 'keywords' => 'Shop, gifts', 'author' => 'A & B'],
        'bg' => ['title' => 'Начало', 'robots' => 'index, nofollow', 'prependSiteTitle' => false, 'author' => 'Иван'],
    ]]);

    $reader = app(SeoReader::class);
    expect($reader->resolve($actor, $owner, 'en'))->toMatchArray(['robots' => 'noindex, nofollow', 'title' => 'Example | Landing', 'keywords' => 'Shop, gifts', 'author' => 'A & B'])
        ->and($reader->resolve($actor, $owner, 'bg'))->toMatchArray(['robots' => 'index, nofollow', 'title' => 'Начало', 'author' => 'Иван']);
    expect(app(SeoHeadRenderer::class)->render($owner, $actor, 'en'))->toContain('<meta name="keywords" content="Shop, gifts">', '<meta name="author" content="A &amp; B">');

    $profiles->sync($actor, $owner, ['translations' => ['bg' => ['robots' => null, 'prependSiteTitle' => null]]]);
    $fallback = $reader->resolve($actor, $owner, 'bg');
    expect($fallback['robots'])->toStartWith('index, follow')
        ->and($fallback['title'])->toBe('Начало | Example')
        ->and($fallback['author'])->toBe('Иван');
});

it('stages a legacy SEO graph with the same record IDs, timestamps and canonical route hashes', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $owner = TestSeoOwner::query()->create(['name' => 'Legacy SEO']);
    $profileId = (string) Str::uuid();
    $translationId = (string) Str::uuid();
    $timestamp = '2026-01-03 12:00:00';

    try {
        DB::table('seo')->insert(['id' => $profileId, 'seoable_type' => 'old-article', 'seoable_id' => $owner->getKey(), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        DB::table('seo_i18n')->insert(['id' => $translationId, 'seo_id' => $profileId, 'locale' => 'en', 'path' => 'legacy/', 'title' => 'Legacy', 'additional_data' => '{"custom":false}', 'robots' => 'noindex', 'prepend_site_title' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp]);

        $actor = SystemActor::Background;
        $source = ['scope' => 'shop', 'owner_aliases' => ['old-article' => 'article']];
        $adopter = app(SeoSchemaAdopter::class);
        $plan = $adopter->plan($actor, $source);
        expect($plan)->toMatchArray(['profiles' => 1, 'translations' => 1, 'staged_profiles' => 0, 'staged_translations' => 0, 'conflicts' => []]);
        expect(SeoProfile::query()->whereKey($profileId)->exists())->toBeFalse();
        expect(fn () => $adopter->verify($actor, $plan['fingerprint'], $source))->toThrow(InvalidSeoMutationException::class, 'not fully staged');

        $staged = $adopter->apply($actor, $plan['fingerprint'], $source);
        expect($staged)->toMatchArray(['staged_profiles' => 1, 'staged_translations' => 1]);
        expect($adopter->verify($actor, $plan['fingerprint'], $source))->toBe($staged)
            ->and($adopter->apply($actor, $plan['fingerprint'], $source))->toBe($staged);

        $profile = SeoProfile::query()->findOrFail($profileId);
        $translation = $profile->translations()->sole();
        expect($profile->seoable->getKey())->toBe($owner->getKey())->and($profile->created_at->toDateTimeString())->toBe($timestamp)
            ->and($translation->id)->toBe($translationId)->and($translation->created_at->toDateTimeString())->toBe($timestamp)
            ->and($translation->path)->toBe('/legacy')->and($translation->path_hash)->toBe(SeoPath::hash('shop', 'en', '/legacy'))
            ->and($translation->metadata)->toBe(['custom' => false])->and($translation->prepend_site_title)->toBeFalse();
    } finally {
        SeoProfile::query()->whereKey($profileId)->delete();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owner->forceDelete();
    }
});

it('reports normalized legacy route collisions before staging any SEO profiles', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $owners = collect([TestSeoOwner::query()->create(['name' => 'Legacy A']), TestSeoOwner::query()->create(['name' => 'Legacy B'])]);
    $profileIds = [(string) Str::uuid(), (string) Str::uuid()];

    try {
        foreach ($owners as $index => $owner) {
            DB::table('seo')->insert(['id' => $profileIds[$index], 'seoable_type' => $owner->getMorphClass(), 'seoable_id' => $owner->getKey()]);
            DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileIds[$index], 'locale' => 'en', 'path' => $index === 0 ? '/shared/' : 'shared']);
        }
        $actor = SystemActor::Background;
        $adopter = app(SeoSchemaAdopter::class);
        $plan = $adopter->plan($actor);
        expect($plan['conflicts'])->toHaveCount(1)->and($plan['conflicts'][0])->toContain($profileIds[0], $profileIds[1], '/shared');
        expect(fn () => $adopter->apply($actor, $plan['fingerprint']))->toThrow(InvalidSeoMutationException::class, 'conflicts');
        expect(SeoProfile::query()->whereKey($profileIds)->count())->toBe(0);
    } finally {
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
    }
});

it('resumes interrupted nvl:adopt SEO batches with profiles matching the legacy adopter', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $adoption = seoAdoptionMigration();
    $adoption->up();
    $owners = collect(range(1, 3))->map(static fn (int $index): TestSeoOwner => TestSeoOwner::query()->create(['name' => "Legacy {$index}"]));
    $profileIds = collect(range(1, 3))->map(static fn (): string => (string) Str::uuid())->sort()->values()->all();
    $timestamp = '2026-01-03 12:00:00';
    $manifest = (string) tempnam(sys_get_temp_dir(), 'nvl-seo-adopt-');
    file_put_contents($manifest, json_encode(['scope' => 'shop', 'owner_aliases' => ['old-article' => 'article']], JSON_THROW_ON_ERROR));
    $translationTable = (new SeoProfileTranslation)->getTable();
    $armed = false;

    try {
        foreach ($profileIds as $index => $profileId) {
            DB::table('seo')->insert(['id' => $profileId, 'seoable_type' => 'old-article', 'seoable_id' => $owners[$index]->getKey(), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            foreach ($index === 1 ? ['en', 'bg'] : ['en'] as $locale) {
                DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileId, 'locale' => $locale, 'path' => "{$locale}/legacy-{$index}/", 'title' => "Legacy {$index}", 'prepend_site_title' => false, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            }
        }
        DB::listen(static function (QueryExecuted $query) use (&$armed, $translationTable, $profileIds): void {
            if ($armed && str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, $translationTable) && in_array($profileIds[1], $query->bindings, true)) {
                $armed = false;

                throw new RuntimeException('Injected SEO adoption failure.');
            }
        });

        $plan = seoAdoptCommand(['manifest' => $manifest]);
        expect($plan)->toMatchArray(['status' => 0, 'state' => 'planned', 'collisions' => []])
            ->and($plan['counts'])->toBe(['profiles' => ['expected' => 3, 'processed' => 0, 'complete' => false]]);

        $armed = true;
        expect(seoAdoptCommand(['phase' => 'apply', 'manifest' => $manifest, '--fingerprint' => $plan['fingerprint'], '--batch-size' => '1']))
            ->toMatchArray(['status' => 1, 'error' => 'Injected SEO adoption failure.']);
        $runId = (string) DB::table(AdoptionStore::Runs)->value('id');
        expect(SeoProfile::query()->whereKey($profileIds)->pluck('id')->all())->toBe([$profileIds[0]])
            ->and(DB::table($translationTable)->where('scope', 'shop')->count())->toBe(1)
            ->and(DB::table(AdoptionStore::Checkpoints)->where('run_id', $runId)->value('processed'))->toEqual(1);

        $applied = seoAdoptCommand(['phase' => 'apply', 'manifest' => $manifest, '--fingerprint' => $plan['fingerprint'], '--run' => $runId, '--batch-size' => '1']);
        expect($applied)->toMatchArray(['status' => 0, 'run' => $runId, 'state' => 'applied'])
            ->and($applied['counts']['profiles'])->toBe(['expected' => 3, 'processed' => 3, 'complete' => true])
            ->and($applied['cursors']['profiles'])->toBe(['id' => $profileIds[2]]);
        expect(seoAdoptCommand(['phase' => 'verify', 'manifest' => $manifest])['verification'])->toMatchArray(['passed' => true, 'missing' => 0, 'extra' => 0, 'changed' => 0])
            ->and(seoAdoptCommand(['phase' => 'activate', 'manifest' => $manifest, '--fingerprint' => $plan['fingerprint']]))->toMatchArray(['status' => 0, 'state' => 'active']);

        $source = ['scope' => 'shop', 'owner_aliases' => ['old-article' => 'article']];
        $adopter = app(SeoSchemaAdopter::class);
        $legacy = $adopter->plan(SystemActor::Background, $source);
        expect($adopter->verify(SystemActor::Background, $legacy['fingerprint'], $source))
            ->toMatchArray(['profiles' => 3, 'translations' => 4, 'staged_profiles' => 3, 'staged_translations' => 4, 'conflicts' => []]);
        $resumed = SeoProfile::query()->findOrFail($profileIds[1]);
        expect($resumed->seoable->getKey())->toBe($owners[1]->getKey())
            ->and($resumed->translations()->orderBy('locale')->pluck('path')->all())->toBe(['/bg/legacy-1', '/en/legacy-1'])
            ->and($resumed->translations()->where('locale', 'en')->value('path_hash'))->toBe(SeoPath::hash('shop', 'en', '/en/legacy-1'));
    } finally {
        $armed = false;
        DB::table($translationTable)->whereIn('seo_profile_id', $profileIds)->delete();
        SeoProfile::query()->whereKey($profileIds)->delete();
        $adoption->down();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
        unlink($manifest);
    }
});

it('refuses to resume SEO adoption after a staged translation behind the cursor was deleted', function (bool $complete): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $adoption = seoAdoptionMigration();
    $adoption->up();
    $owners = collect(range(1, 2))->map(static fn (int $index): TestSeoOwner => TestSeoOwner::query()->create(['name' => "Legacy {$index}"]));
    $profileIds = collect(range(1, 2))->map(static fn (): string => (string) Str::uuid())->sort()->values()->all();
    $manifest = (string) tempnam(sys_get_temp_dir(), 'nvl-seo-adopt-');
    file_put_contents($manifest, json_encode(['scope' => 'shop', 'owner_aliases' => ['old-article' => 'article']], JSON_THROW_ON_ERROR));
    $translationTable = (new SeoProfileTranslation)->getTable();
    $armed = false;

    try {
        foreach ($profileIds as $index => $profileId) {
            DB::table('seo')->insert(['id' => $profileId, 'seoable_type' => 'old-article', 'seoable_id' => $owners[$index]->getKey()]);
            foreach (['en', 'bg'] as $locale) {
                DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileId, 'locale' => $locale, 'path' => "{$locale}/legacy-{$index}"]);
            }
        }
        DB::listen(static function (QueryExecuted $query) use (&$armed, $translationTable, $profileIds): void {
            if ($armed && str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, $translationTable) && in_array($profileIds[1], $query->bindings, true)) {
                $armed = false;

                throw new RuntimeException('Injected SEO adoption failure.');
            }
        });
        $plan = seoAdoptCommand(['manifest' => $manifest]);
        $armed = ! $complete;
        $first = seoAdoptCommand(['phase' => 'apply', 'manifest' => $manifest, '--fingerprint' => $plan['fingerprint'], '--batch-size' => '1']);
        expect($first['status'])->toBe($complete ? 0 : 1);
        $runId = (string) DB::table(AdoptionStore::Runs)->value('id');
        DB::table($translationTable)->where('seo_profile_id', $profileIds[0])->where('locale', 'bg')->delete();
        $staged = DB::table($translationTable)->where('scope', 'shop')->count();

        $resumed = seoAdoptCommand(['phase' => 'apply', 'manifest' => $manifest, '--fingerprint' => $plan['fingerprint'], '--run' => $runId, '--batch-size' => '1']);
        expect($resumed['status'])->toBe(1)
            ->and($resumed['error'])->toContain('deleted staged SEO row')
            ->and(DB::table($translationTable)->where('scope', 'shop')->count())->toBe($staged)
            ->and(SeoProfile::query()->whereKey($profileIds)->count())->toBe($complete ? 2 : 1)
            ->and(DB::table(AdoptionStore::Checkpoints)->where('run_id', $runId)->value('processed'))->toEqual($complete ? 2 : 1);
    } finally {
        $armed = false;
        DB::table($translationTable)->whereIn('seo_profile_id', $profileIds)->delete();
        SeoProfile::query()->whereKey($profileIds)->delete();
        $adoption->down();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
        unlink($manifest);
    }
})->with(['incomplete cursor' => [false], 'completed step' => [true]]);

it('refuses an SEO adoption checkpoint after a legacy source profile behind the cursor was deleted', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $adoption = seoAdoptionMigration();
    $adoption->up();
    $owners = collect(range(1, 2))->map(static fn (int $index): TestSeoOwner => TestSeoOwner::query()->create(['name' => "Legacy {$index}"]));
    $profileIds = collect(range(1, 2))->map(static fn (): string => (string) Str::uuid())->sort()->values()->all();
    $translationTable = (new SeoProfileTranslation)->getTable();
    $adapter = static fn (): AdoptionAdapter => app(AdoptionRegistry::class)->make('seo', SystemActor::Background);

    try {
        foreach ($profileIds as $index => $profileId) {
            DB::table('seo')->insert(['id' => $profileId, 'seoable_type' => $owners[$index]->getMorphClass(), 'seoable_id' => $owners[$index]->getKey()]);
            DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileId, 'locale' => 'en', 'path' => "legacy-{$index}"]);
        }
        $coordinator = new AdoptionCoordinator($adapter());
        $coordinator->apply($coordinator->plan()->fingerprint);
        $step = new AdoptionStep('profiles');
        $cursor = ['id' => $profileIds[1]];
        $adapter()->assertCheckpoint($step, $cursor, 2);

        DB::table('seo_i18n')->where('seo_id', $profileIds[0])->delete();
        DB::table('seo')->where('id', $profileIds[0])->delete();

        expect(fn () => $adapter()->assertCheckpoint($step, $cursor, 2))
            ->toThrow(AdoptionException::class, 'counts 2 legacy SEO profiles, but 1 precede its cursor');
    } finally {
        DB::table($translationTable)->whereIn('seo_profile_id', $profileIds)->delete();
        SeoProfile::query()->whereKey($profileIds)->delete();
        $adoption->down();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
    }
});

it('blocks SEO activation when a duplicate legacy route appears after verification', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $adoption = seoAdoptionMigration();
    $adoption->up();
    $owners = collect([TestSeoOwner::query()->create(['name' => 'Legacy A']), TestSeoOwner::query()->create(['name' => 'Legacy B'])]);
    $profileIds = [(string) Str::uuid(), (string) Str::uuid()];
    $legacy = static function (int $index) use ($owners, $profileIds): void {
        DB::table('seo')->insert(['id' => $profileIds[$index], 'seoable_type' => $owners[$index]->getMorphClass(), 'seoable_id' => $owners[$index]->getKey()]);
        DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileIds[$index], 'locale' => 'en', 'path' => $index === 0 ? '/shared/' : 'shared']);
    };
    $coordinator = static fn (): AdoptionCoordinator => new AdoptionCoordinator(app(AdoptionRegistry::class)->make('seo', SystemActor::Background));

    try {
        $legacy(0);
        $plan = $coordinator()->plan();
        $coordinator()->apply($plan->fingerprint);
        expect($coordinator()->verify()->passed)->toBeTrue();

        $legacy(1);
        $replanned = $coordinator()->plan();
        expect($replanned->collisions)->toHaveCount(1)
            ->and($replanned->collisions[0])->toContain($profileIds[0], $profileIds[1], '/shared');
        expect(fn () => $coordinator()->activate($plan->fingerprint))->toThrow(AdoptionException::class, 'changed since review');
        expect(fn () => $coordinator()->activate($replanned->fingerprint))->toThrow(AdoptionException::class, 'different fingerprint');
        expect(DB::table(AdoptionStore::Runs)->value('activated_at'))->toBeNull()
            ->and(SeoProfile::query()->whereKey($profileIds)->pluck('id')->all())->toBe([$profileIds[0]]);
    } finally {
        SeoProfile::query()->whereKey($profileIds)->delete();
        $adoption->down();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
    }
});

it('blocks SEO activation through verification when a native row joins the scope after verification', function (): void {
    $this->commitDatabaseFixture();
    createLegacySeoSchema();
    $adoption = seoAdoptionMigration();
    $adoption->up();
    $owners = collect([TestSeoOwner::query()->create(['name' => 'Legacy A']), TestSeoOwner::query()->create(['name' => 'Native B'])]);
    $profileIds = [(string) Str::uuid(), (string) Str::uuid()];
    $translationTable = (new SeoProfileTranslation)->getTable();
    $coordinator = static fn (): AdoptionCoordinator => new AdoptionCoordinator(app(AdoptionRegistry::class)->make('seo', SystemActor::Background));

    try {
        DB::table('seo')->insert(['id' => $profileIds[0], 'seoable_type' => $owners[0]->getMorphClass(), 'seoable_id' => $owners[0]->getKey()]);
        DB::table('seo_i18n')->insert(['id' => (string) Str::uuid(), 'seo_id' => $profileIds[0], 'locale' => 'en', 'path' => '/shared/']);
        $plan = $coordinator()->plan();
        $coordinator()->apply($plan->fingerprint);
        expect($coordinator()->verify()->passed)->toBeTrue();

        $profile = (array) DB::table(SeoTables::Profiles)->where('id', $profileIds[0])->first();
        $translation = (array) DB::table($translationTable)->where('seo_profile_id', $profileIds[0])->first();
        DB::table(SeoTables::Profiles)->insert([...$profile, 'id' => $profileIds[1], 'seoable_id' => $owners[1]->getKey()]);
        DB::table($translationTable)->insert([...$translation, 'id' => (string) Str::uuid(), 'seo_profile_id' => $profileIds[1], 'locale' => 'bg', 'path_hash' => SeoPath::hash($translation['scope'], 'bg', $translation['path'])]);

        expect($coordinator()->plan()->fingerprint)->toBe($plan->fingerprint);
        expect(fn () => $coordinator()->activate($plan->fingerprint))->toThrow(AdoptionException::class, 'changed since verification');
        $verification = $coordinator()->verify();
        expect($verification->passed)->toBeFalse()
            ->and($verification->extra)->toBe(2)
            ->and(DB::table(AdoptionStore::Runs)->value('status'))->toBe('applied');
        expect(fn () => $coordinator()->activate($plan->fingerprint))->toThrow(AdoptionException::class, 'requires a passed verification');
        expect(DB::table(AdoptionStore::Runs)->value('activated_at'))->toBeNull();
    } finally {
        DB::table($translationTable)->whereIn('seo_profile_id', $profileIds)->delete();
        SeoProfile::query()->whereKey($profileIds)->delete();
        $adoption->down();
        Schema::dropIfExists('seo_i18n');
        Schema::dropIfExists('seo');
        $owners->each->forceDelete();
    }
});

it('namespaces default sitemap cache identities and preserves host-selected prefixes', function (): void {
    $store = new ArrayStore;
    $repository = new CacheRepository($store);
    $cache = new SitemapCache($repository, app(SitemapArtifactStore::class));
    $identity = $cache->capture('default');
    $foreignKey = str_replace('nvl:seo:sitemap:', 'nvl-seo:sitemap:', $identity['key']);
    $repository->forever($foreignKey, 'host-value');

    expect($identity['key'])->toStartWith('nvl:seo:sitemap:');
    $cache->forgetCaptured($identity);
    expect($repository->get($foreignKey))->toBe('host-value');

    config()->set('nvl-seo.sitemap.cache_key', 'host-selected-sitemaps');
    expect($cache->capture('default')['key'])->toStartWith('host-selected-sitemaps:');
});

it('accepts string-encoded integer versions from string-backed cache stores like redis', function (): void {
    $store = new ArrayStore;
    $cacheRepository = new CacheRepository($store);
    $cache = new SitemapCache($cacheRepository, app(SitemapArtifactStore::class));

    $identity = $cache->capture('default');
    expect($identity['version'])->toBe(1);

    $baseKey = config('nvl-seo.sitemap.cache_key', 'nvl:seo:sitemap');
    $facts = [
        'connection' => $identity['connection'],
        'tenant' => $identity['tenantId'],
        'site' => $identity['site'],
        'origin' => $identity['origin'],
        'scope' => $identity['scope'],
    ];
    $versionKey = $baseKey.':'.hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR)).':version';
    $cacheRepository->forever($versionKey, '3');

    $updated = $cache->capture('default');
    expect($updated['version'])->toBe(3);

    expect($cache->forgetCaptured($updated))->toBeTrue();
});

it('keeps non-default sitemap index and chunk requests in the requested scope', function (): void {
    config()->set([
        'nvl-seo.routes.sitemap_scopes' => ['wholesale'],
        'nvl-seo.sitemap.max_urls' => 1,
    ]);
    $owner = TestSeoOwner::query()->create(['name' => 'Scoped sitemap owner']);
    app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => [
                    'path' => '/wholesale/product',
                    'title' => 'Wholesale product',
                ],
                'bg' => [
                    'path' => '/wholesale/bg/product',
                    'title' => 'Продукт на едро',
                ],
            ],
        ],
        'wholesale',
    );

    $index = app(SitemapGenerator::class)->generate('wholesale');

    expect($index)->toContain(
        'sitemap-1.xml?scope=wholesale',
        'sitemap-2.xml?scope=wholesale',
    );
    $this->get('/sitemap-1.xml?scope=wholesale')
        ->assertOk()
        ->assertSee('https://example.test/wholesale/', false);
    $this->getJson('/sitemap-1.xml?scope[]=wholesale')
        ->assertUnprocessable();
    $this->getJson('/sitemap-1.xml?scope=unregistered')
        ->assertUnprocessable();
});

it('omits valid external canonical profiles from the built in sitemap', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'External canonical owner']);
    app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => [
                    'path' => '/duplicate',
                    'canonicalUrl' => 'https://canonical.example.org/original',
                    'title' => 'Duplicate',
                ],
                'bg' => [
                    'path' => '/local-copy',
                    'title' => 'Локално копие',
                ],
            ],
        ],
    );

    $xml = app(SitemapGenerator::class)->generate();

    expect($xml)->toContain(
        '<urlset',
        '<loc>https://example.test/local-copy</loc>',
        'href="https://canonical.example.org/original"',
    )
        ->and($xml)
        ->not->toContain('<loc>https://canonical.example.org/original</loc>')
        ->not->toContain('<loc>https://example.test/duplicate</loc>');
});

it('treats unregistered runtime owner types as advisory until management is enabled', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Unregistered runtime owner']);
    app(SeoProfiles::class)->sync(SystemActor::Background,
        $owner,
        [
            'translations' => [
                'en' => ['path' => '/runtime-owner'],
            ],
        ],
    );
    config()->set('nvl-seo.owners', []);
    $doctor = app(SeoDoctor::class);
    $advisory = collect($doctor->inspect())->firstWhere('key', 'configuration.owners');

    expect($advisory['passed'])->toBeFalse()
        ->and($advisory['severity'])->toBe('warning');

    config()->set('nvl-seo.management.enabled', true);
    $required = collect($doctor->inspect())->firstWhere('key', 'configuration.owners');

    expect($required['passed'])->toBeFalse()
        ->and($required['severity'])->toBe('error');
});

it('rejects import sources that exceed the requested bounded page', function (): void {
    $owner = TestSeoOwner::query()->create(['name' => 'Bounded import owner']);
    $record = ['ownerAlias' => 'article', 'ownerId' => (string) $owner->getKey(), 'scope' => null, 'profile' => [
        'translations' => [
            'en' => ['path' => '/bounded-import'],
        ],
    ]];
    $source = new class($record) implements SeoImportSource
    {
        public function __construct(
            private readonly array $record,
        ) {}

        public function page(?string $cursor, int $limit): array
        {
            return ['items' => [$this->record, $this->record], 'nextCursor' => null];
        }
    };

    expect(fn () => app(SeoProfiles::class)->import(SystemActor::Background,
        $source,
        limit: 1,
    ))
        ->toThrow(LogicException::class, 'more than its requested')
        ->and(SeoProfile::query()->count())->toBe(0);
});

it('fails closed for invalid management middleware and doctor output formats', function (): void {
    config()->set('nvl-seo.management.middleware', []);

    expect(fn () => require __DIR__.'/../Fixtures/management.php')
        ->toThrow(InvalidArgumentException::class, 'non-empty middleware');

    $this->artisan('nvl:seo:doctor', ['--format' => 'yaml'])
        ->assertExitCode(Command::INVALID);
});

it('publishes sitemap xml to immutable filesystem artifacts and clears them atomically', function (): void {
    $this->commitDatabaseFixture();
    Storage::fake('seo-artifacts');
    config()->set('nvl-seo.sitemap.disk', 'seo-artifacts');
    $cache = app(SitemapCache::class);
    $namespace = $cache->namespace('default');

    $artifact = "nvl-seo/sitemaps/{$namespace}/chunk-1.xml";

    $this->artisan('nvl:seo:sitemap:warm')
        ->assertSuccessful();

    expect(Storage::disk('seo-artifacts')->exists($artifact))->toBeTrue();

    $this->artisan('nvl:seo:sitemap:clear')
        ->assertSuccessful();

    expect(Storage::disk('seo-artifacts')->exists($artifact))->toBeFalse()
        ->and($cache->namespace('default'))->not->toBe($namespace);

    $this->artisan('nvl:seo:sitemap:clear', ['--scope' => 'invalid scope'])
        ->assertExitCode(Command::INVALID);

    config()->set('nvl-seo.sitemap.cache_seconds', 0);
    $this->artisan('nvl:seo:sitemap:warm')
        ->assertExitCode(Command::INVALID);
});

it('refuses a sitemap origin with a clear error when SEO declares no owned resources', function (): void {
    Storage::fake('seo-artifacts');
    config()->set('nvl-seo.sitemap.disk', 'seo-artifacts');

    $this->artisan('nvl:seo:sitemap:warm', ['--origin' => 'https://a.seo.test'])
        ->expectsOutputToContain('The --origin option requires owned SEO resources')
        ->assertExitCode(Command::INVALID);

    expect(Storage::disk('seo-artifacts')->allFiles())->toBe([]);
});

it('prefers localized redirects and falls back to locale-neutral redirects', function (): void {
    $action = app(SeoRedirects::class);
    $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/legacy',
        'target' => '/global-destination',
    ]);
    $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/legacy',
        'target' => '/localized-destination',
        'locale' => 'bg',
    ]);
    $resolver = app(SeoRedirects::class);

    expect($resolver->resolve(SystemActor::Background, '/legacy', 'bg')['target'])->toBe('/localized-destination')
        ->and($resolver->resolve(SystemActor::Background, '/legacy', 'en')['target'])->toBe('/global-destination')
        ->and($resolver->resolve(SystemActor::Background, '/legacy')['target'])->toBe('/global-destination');
});

it('rejects direct and chained loops through same-site absolute urls', function (): void {
    $action = app(SeoRedirects::class);

    expect(fn () => $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/self',
        'target' => 'https://example.test/self?campaign=legacy',
    ]))->toThrow(SeoRedirectLoopException::class);

    $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/return',
        'target' => '/origin',
    ]);

    expect(fn () => $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/origin',
        'target' => 'https://example.test/return',
    ]))->toThrow(SeoRedirectLoopException::class);

    $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/absolute-return',
        'target' => 'https://example.test/absolute-origin',
    ]);

    expect(fn () => $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/absolute-origin',
        'target' => '/absolute-return',
    ]))->toThrow(SeoRedirectLoopException::class);
});

it('rejects neutral redirects that close a localized fallback loop', function (): void {
    $action = app(SeoRedirects::class);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/a', 'target' => '/b', 'locale' => 'en']);

    expect(fn () => $action->sync(SystemActor::Background, null, ['sourcePath' => '/b', 'target' => '/a']))
        ->toThrow(SeoRedirectLoopException::class);
    expect(app(SeoRedirects::class)->resolve(SystemActor::Background, '/b', 'en'))->toBeNull();
});

it('retires an inactive redirect while its reverse redirect remains active', function (): void {
    $redirects = app(SeoRedirects::class);
    $retired = $redirects->sync(SystemActor::Background, null, ['sourcePath' => '/old-brand', 'target' => '/new-brand']);
    $retired = $redirects->sync(SystemActor::Background, $retired, ['sourcePath' => '/old-brand', 'target' => '/new-brand', 'isActive' => false, 'expected_revision' => $retired->revision]);
    $redirects->sync(SystemActor::Background, null, ['sourcePath' => '/new-brand', 'target' => '/old-brand']);

    $retired = $redirects->sync(SystemActor::Background, $retired, ['sourcePath' => '/old-brand', 'target' => '/new-brand', 'isActive' => false, 'expected_revision' => $retired->revision]);

    expect($retired->is_active)->toBeFalse()
        ->and($retired->target)->toBe('/new-brand')
        ->and($redirects->resolve(SystemActor::Background, '/old-brand'))->toBeNull()
        ->and($redirects->resolve(SystemActor::Background, '/new-brand')['target'])->toBe('/old-brand')
        ->and(fn () => $redirects->sync(SystemActor::Background, $retired, ['sourcePath' => '/old-brand', 'target' => '/new-brand', 'expected_revision' => $retired->revision]))
        ->toThrow(SeoRedirectLoopException::class);
});

it('rejects updates that expose a loop through neutral fallback', function (): void {
    $action = app(SeoRedirects::class);
    $shadow = $action->sync(SystemActor::Background, null, ['sourcePath' => '/a', 'target' => '/safe', 'locale' => 'en']);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/b', 'target' => '/a?from=legacy', 'locale' => 'en']);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/a', 'target' => '/b']);

    expect(fn () => $action->sync(SystemActor::Background, $shadow, ['sourcePath' => '/a', 'target' => '/safe', 'locale' => 'en', 'isActive' => false, 'expected_revision' => $shadow->revision]))->toThrow(SeoRedirectLoopException::class);
    expect($shadow->refresh()->is_active)->toBeTrue();
});

it('allows neutral redirects whose localized source is safely shadowed', function (): void {
    $action = app(SeoRedirects::class);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/b', 'target' => '/safe', 'locale' => 'en']);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/a', 'target' => '/b', 'locale' => 'en']);
    $action->sync(SystemActor::Background, null, ['sourcePath' => '/b', 'target' => '/a']);

    expect(app(SeoRedirects::class)->resolve(SystemActor::Background, '/b', 'en')['target'])->toBe('/safe')
        ->and(app(SeoRedirects::class)->resolve(SystemActor::Background, '/b')['target'])->toBe('/a');
});

it('increments a restored redirect revision exactly once', function (): void {
    $action = app(SeoRedirects::class);
    $redirect = $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/restored',
        'target' => '/original-target',
        'metadata' => ['source' => 'legacy-import'],
        'expected_revision' => 0,
    ]);
    $redirect->delete();
    $restored = $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/restored',
        'target' => '/current-target',
        'expected_revision' => 0,
    ]);

    expect($restored->revision)->toBe($redirect->revision + 1)
        ->and($restored->target)->toBe('/current-target')
        ->and($restored->metadata)->toBe(['source' => 'legacy-import'])
        ->and($restored->trashed())->toBeFalse();
});

it('prunes only redirect tombstones outside the requested retention window', function (): void {
    $action = app(SeoRedirects::class);
    $old = $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/old-deleted',
        'target' => '/destination',
    ]);
    $recent = $action->sync(SystemActor::Background, null, [
        'sourcePath' => '/recently-deleted',
        'target' => '/destination',
    ]);
    $old->delete();
    $recent->delete();
    SeoRedirect::onlyTrashed()->whereKey($old->id)->update([
        'deleted_at' => now()->subDays(31),
    ]);

    $this->artisan('nvl:seo:redirects:prune', ['--days' => '30'])
        ->assertSuccessful();

    expect(SeoRedirect::withTrashed()->find($old->id))->toBeNull()
        ->and(SeoRedirect::withTrashed()->find($recent->id))->toBeInstanceOf(SeoRedirect::class);
});

it('rejects robots directive injection and oversized output', function (): void {
    config()->set('nvl-seo.robots.allow', ["/safe\r\nDisallow: /private"]);

    expect(fn () => app(RobotsGenerator::class)->generate())
        ->toThrow(LogicException::class, 'non-empty crawler path');

    config()->set([
        'nvl-seo.robots.allow' => ['/'],
        'nvl-seo.robots.maximum_bytes' => 20,
    ]);

    expect(fn () => app(RobotsGenerator::class)->generate())
        ->toThrow(LogicException::class, 'exceeds the configured')
        ->and(collect(app(SeoDoctor::class)->inspect())
            ->firstWhere('key', 'configuration.robots')['passed'])
        ->toBeFalse();
});

it('reports an unapplied redirect lock migration as an unhealthy schema', function (): void {
    Schema::drop(SeoTables::RedirectLocks);

    expect(collect(app(SeoDoctor::class)->inspect())
        ->firstWhere('key', 'schema.table.'.SeoTables::RedirectLocks)['passed'])->toBeFalse();
    $this->artisan('nvl:seo:doctor', ['--strict' => true, '--format' => 'json'])->assertFailed();
});

it('rolls package migrations back cleanly and fails loudly on an occupied table', function (): void {
    $directory = __DIR__.'/../../database/migrations';
    $this->artisan('migrate:rollback', ['--path' => [$directory], '--realpath' => true, '--step' => count(glob($directory.'/*.php') ?: []), '--force' => true])
        ->assertSuccessful();

    expect(Schema::hasTable(SeoTables::Profiles))->toBeFalse()
        ->and(Schema::hasTable(SeoTables::I18n))->toBeFalse()
        ->and(Schema::hasTable(SeoTables::Redirects))->toBeFalse()
        ->and(Schema::hasTable(SeoTables::RedirectLocks))->toBeFalse();

    Schema::create(SeoTables::Profiles, function (Blueprint $table): void {
        $table->uuid('id')->primary();
    });

    expect(fn () => $this->artisan('migrate', ['--force' => true])->run())
        ->toThrow(QueryException::class, 'already exists');

    Schema::drop(SeoTables::Profiles);

    $this->artisan('migrate', ['--force' => true])
        ->assertSuccessful();

    expect(Schema::hasTable(SeoTables::Profiles))->toBeTrue()
        ->and(Schema::hasTable(SeoTables::I18n))->toBeTrue()
        ->and(Schema::hasColumns(SeoTables::I18n, ['robots', 'prepend_site_title', 'keywords', 'author']))->toBeTrue()
        ->and(Schema::hasTable(SeoTables::Redirects))->toBeTrue()
        ->and(Schema::hasTable(SeoTables::RedirectLocks))->toBeTrue();
});
