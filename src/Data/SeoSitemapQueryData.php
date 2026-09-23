<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Illuminate\Validation\Rule;
use Nvl\Seo\Support\SeoScope;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated public sitemap selector. */
#[TypeScript]
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
