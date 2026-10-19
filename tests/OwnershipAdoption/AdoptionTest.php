<?php

declare(strict_types=1);

use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoRedirectResolver;
use Nvl\Seo\Tests\LegacyOwnershipTestCase;

it('adopts reviewed tenant records without changing native subjects copy or public redirect behavior', function (): void {
    $this->owned(7, function (): void {
        $profile = SeoProfile::findOrFail(LegacyOwnershipTestCase::PROFILE);
        expect($profile->translations->first()->title)->toBe('Legacy SEO')
            ->and(app(SeoRedirectResolver::class)->resolve('/old-legacy')?->target)->toBe('/legacy');
    });
    $this->owned(8, fn () => expect(SeoProfile::count())->toBe(0));
});
