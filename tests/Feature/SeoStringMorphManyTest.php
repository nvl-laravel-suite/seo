<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Tests\Fixtures\TestIntegerSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestStringSeoOwner;
use Nvl\Seo\Traits\HasSeo;

it('supports native owner keys across relation reads writes and existence queries', function (string $ownerClass): void {
    $owner = $ownerClass::query()->create(['name' => 'Primary owner']);
    $emptyOwner = $ownerClass::query()->create(['name' => 'Empty owner']);

    $profile = $owner->seoProfiles()
        ->withAttributes(['metadata' => ['source' => 'relation']])
        ->create(['scope' => 'default']);

    expect($profile->seoable_id)->toBe((string) $owner->getKey())
        ->and($profile->seoable_type)->toBe($owner->getMorphClass())
        ->and($profile->metadata)->toBe(['source' => 'relation'])
        ->and($owner->fresh()?->seoProfiles)->toHaveCount(1)
        ->and($ownerClass::query()
            ->with('seoProfiles')
            ->findOrFail($owner->getKey())
            ->seoProfiles)->toHaveCount(1)
        ->and($ownerClass::query()
            ->with('seoProfiles')
            ->findOrFail($emptyOwner->getKey())
            ->seoProfiles)->toBeEmpty()
        ->and($ownerClass::query()
            ->whereHas('seoProfiles')
            ->pluck('id')
            ->all())->toBe([$owner->getKey()]);
})->with([
    'integer primary key' => [TestIntegerSeoOwner::class],
    'UUID primary key' => [TestSeoOwner::class],
]);

it('handles absent keys and rejects compound owner identifiers', function (): void {
    $unsaved = new TestIntegerSeoOwner;

    expect($unsaved->seoProfiles()->toSql())->toBeString();

    $compoundOwner = new class extends Model
    {
        use HasSeo;

        public function getAttribute($key): mixed
        {
            return $key === $this->getKeyName()
                ? ['compound']
                : parent::getAttribute($key);
        }
    };
    $relation = Relation::noConstraints(
        fn () => $compoundOwner->seoProfiles(),
    );

    expect(fn () => $relation->make())
        ->toThrow(
            InvalidArgumentException::class,
            'SEO owner identifiers must be integers or strings.',
        );
});

it('applies eager relation limits independently to case-distinct owner identities', function (): void {
    $driver = DB::connection()->getDriverName();
    if ($driver === 'sqlite') {
        Schema::table(SeoTables::get(SeoTables::Profiles), static function (Blueprint $table): void {
            $table->string('seoable_id')->collation('NOCASE')->change();
        });
    }
    $owner = new TestStringSeoOwner;
    $owner->forceFill(['id' => 'Owner-A', 'name' => 'Upper'])->save();
    $foreign = new TestStringSeoOwner;
    $foreign->forceFill(['id' => 'owner-a', 'name' => 'Lower'])->save();
    $profile = $owner->seoProfiles()->create(['scope' => 'first']);
    $foreignProfile = $foreign->seoProfiles()->create(['scope' => 'second']);

    $loaded = TestStringSeoOwner::query()->with([
        'seoProfiles' => static fn ($relation) => $relation->orderBy('scope')->limit(1),
    ])->get()->keyBy('id');

    expect($loaded['Owner-A']->seoProfiles->modelKeys())->toBe([$profile->getKey()])
        ->and($loaded['owner-a']->seoProfiles->modelKeys())->toBe([$foreignProfile->getKey()]);
});

it('rejects unsupported legacy MySQL eager partitions before SQL compilation', function (): void {
    $parent = new TestIntegerSeoOwner;
    $relation = $parent->seoProfiles();
    $query = $relation->getQuery()->getQuery();
    $query->grammar = new class($query->getConnection()) extends MySqlGrammar
    {
        public function useLegacyGroupLimit(QueryBuilder $query): bool
        {
            return true;
        }
    };

    expect(fn () => $relation->limit(1)->toSql())
        ->toThrow(LogicException::class, 'require window-function capable MySQL');
});
