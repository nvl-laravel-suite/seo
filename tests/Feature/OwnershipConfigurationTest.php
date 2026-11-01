<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Tests\Fixtures\OwnershipContentOwner;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Ownership\Exceptions\OwnershipConfigurationInvalid;

it('rejects a late native host registration into ordinary unscoped SEO', function (): void {
    expect(fn () => app(SeoOwnerRegistry::class)->register('native-late', OwnershipContentOwner::class))
        ->toThrow(OwnershipConfigurationInvalid::class);
});

it('prunes the configured SEO source while the application default database differs', function (): void {
    $redirect = SeoRedirect::factory()->create(['deleted_at' => now()->subDays(40)]);
    $source = $redirect->getConnection();
    $table = $redirect->getTable();
    config(['nvl-seo.connection' => $source->getName(), 'database.connections.foreign_prune_default' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $foreign = DB::connection('foreign_prune_default');
    $foreign->getSchemaBuilder()->create($table, static function (Blueprint $blueprint): void {
        $blueprint->string('id')->primary();
        $blueprint->timestamp('deleted_at')->nullable();
    });
    $foreign->table($table)->insert(['id' => 'foreign-host-row', 'deleted_at' => now()->subDays(40)]);
    $defaultConnection = DB::getDefaultConnection();
    try {
        DB::setDefaultConnection('foreign_prune_default');
        expect(app(SeoRedirects::class)->prune(SystemActor::Background))->toBe(1);
        expect($source->table($table)->where('id', $redirect->id)->exists())->toBeFalse()
            ->and($foreign->table($table)->where('id', 'foreign-host-row')->exists())->toBeTrue();
    } finally {
        DB::setDefaultConnection($defaultConnection);
    }
});
