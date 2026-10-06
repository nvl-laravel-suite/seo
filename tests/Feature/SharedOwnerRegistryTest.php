<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Tests\Fixtures\TestIntegerSeoOwner;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Support\OwnerRegistry;

beforeEach(function (): void {
    $this->originalOwnerMorphMap = Relation::morphMap();
});

afterEach(function (): void {
    Relation::morphMap($this->originalOwnerMorphMap, false);
});

it('references shared aliases while preserving the seo capability allowlist', function (): void {
    Relation::morphMap(['article' => TestSeoOwner::class]);
    config()->set('nvl-core.owners', ['article' => TestSeoOwner::class, 'integer-article' => TestIntegerSeoOwner::class]);
    config()->set('nvl-seo.owners', ['detail' => 'article']);
    $registry = app(SeoOwnerRegistry::class);

    expect($registry->modelClass('detail'))->toBe(TestSeoOwner::class)
        ->and($registry->morphTypeForAlias('detail'))->toBe('article')
        ->and($registry->aliasForMorphType(TestSeoOwner::class))->toBe('detail')
        ->and(fn () => $registry->modelClass('integer-article'))->toThrow(InvalidSeoMutationException::class);
});

it('keeps historical class-backed seo writes until an owner alias is explicitly declared', function (): void {
    $registry = app(SeoOwnerRegistry::class);

    expect($registry->morphTypeForAlias('article'))->toBe(TestSeoOwner::class)
        ->and(app(OwnerRegistry::class)->model('article'))->toBe(TestSeoOwner::class);
});

it('allows identical code registration through a shared seo reference', function (): void {
    config()->set('nvl-core.owners', ['article' => TestSeoOwner::class]);
    config()->set('nvl-seo.owners', []);
    $registry = app(SeoOwnerRegistry::class);
    $registry->register('detail', 'article');
    $registry->register('detail', 'article');

    expect($registry->modelClass('detail'))->toBe(TestSeoOwner::class)
        ->and(app(OwnerRegistry::class)->deprecations())->not->toHaveKey('seo.owners.detail');
});
