<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Illuminate\Validation\Rule;
use Nvl\Seo\Support\SeoScope;
use Spatie\LaravelData\Data;

/** Validated public sitemap selector. */
final class SeoSitemapQueryData extends Data
{
    public function __construct(public readonly ?string $scope = null) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::in(SeoScope::publicSitemapScopes())],
        ];
    }
}
