<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Nvl\Ownership\Tests\Fixtures\OptsIntoAdoptedSchemaReuse;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Ownership\SeoResourceRegistrar;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;

uses(OptsIntoAdoptedSchemaReuse::class);

it('isolates duplicate profile paths redirects and canonical parents by owner', function (): void {
    $profiles = [];
    foreach ([7, 8] as $id) {
        $profiles[$id] = $this->owned($id, function () use ($id): SeoProfile {
            $owner = app(OwnershipWriter::class)->create(OwnershipContentOwner::class, fn (array $attributes) => OwnershipContentOwner::create([...$attributes, 'name' => 'Subject '.$id]));
            app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/old', 'target' => '/new-'.$id]);

            return app(SeoProfiles::class)->sync(SystemActor::Background, $owner, ['translations' => ['en' => ['path' => '/same', 'title' => 'Owner '.$id]]]);
        });
    }
    $this->owned(7, function () use ($profiles): void {
        expect(SeoProfile::count())->toBe(1)
            ->and(app(SeoRedirects::class)->resolve(SystemActor::Background, '/old')['target'])->toBe('/new-7');
        expect(fn () => $profiles[8]->delete())->toThrow(OwnershipViolation::class);
    });
});

it('keeps ownership redirect locks independent from released tenant lock rows', function (): void {
    $table = SeoTables::get(SeoTables::RedirectLocks);
    $connection = (new SeoProfile)->getConnection();
    $connection->getSchemaBuilder()->drop($table);
    $connection->getSchemaBuilder()->create($table, static function (Blueprint $blueprint): void {
        $blueprint->uuid('tenant_id');
        $blueprint->string('name', 32);
        $blueprint->primary(['tenant_id', 'name']);
    });
    $this->owned(7, function (): void {
        expect(app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/legacy-lock', 'target' => '/target'])->source_path)->toBe('/legacy-lock');
    });
})->group('fresh-adoption');

it('enforces canonical host scope for raw polymorphic profile writes', function (): void {
    $hosts = [];
    foreach ([7, 8] as $id) {
        $hosts[$id] = $this->owned($id, fn () => app(OwnershipWriter::class)->create(OwnershipContentOwner::class, fn (array $attributes) => OwnershipContentOwner::create([...$attributes, 'name' => 'Raw host'])));
    }
    $profile = new SeoProfile;
    expect(fn () => $profile->getConnection()->table($profile->getTable())->insert([
        'id' => (string) Str::uuid(), 'owner_scope_key' => $hosts[7]->getRawOriginal('owner_scope_key'),
        'seoable_type' => $hosts[8]->getMorphClass(), 'seoable_id' => $hosts[8]->id,
    ]))->toThrow(QueryException::class);
});

it('rejects native owned SEO hosts when the profile graph is unscoped', function (): void {
    config(['nvl-seo.ownership.owner_resources' => [], 'nvl-seo.owners' => ['subjects' => OwnershipContentOwner::class]]);
    expect(fn () => (new SeoResourceRegistrar)->register(new OwnershipResourceRegistry))
        ->toThrow(OwnershipConfigurationInvalid::class);
});

it('prunes only the admitted owner tombstones through the canonical SEO source', function (): void {
    $redirects = [];
    foreach ([7, 8] as $id) {
        $redirects[$id] = $this->owned($id, function () use ($id): SeoRedirect {
            $redirect = app(SeoRedirects::class)->sync(SystemActor::Background, null, ['sourcePath' => '/pruned-'.$id, 'target' => '/target']);
            $redirect->delete();
            $redirect->getConnection()->table($redirect->getTable())->where('id', $redirect->id)->update(['deleted_at' => now()->subDays(40)]);

            return $redirect;
        });
    }
    $this->owned(7, function () use ($redirects): void {
        expect(app(SeoRedirects::class)->prune(SystemActor::Background))->toBe(1);
        expect($redirects[8]->getConnection()->table($redirects[8]->getTable())->where('id', $redirects[8]->id)->exists())->toBeTrue();
    });
    expect(fn () => app(SeoRedirects::class)->prune(SystemActor::Background))->toThrow(OwnershipViolation::class);
});
