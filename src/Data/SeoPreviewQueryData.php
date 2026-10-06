<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Illuminate\Validation\Rule;
use Nvl\Support\Facades\Locales;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated optional preview locale. */
#[TypeScript]
final class SeoPreviewQueryData extends SeoManagementInputData
{
    public function __construct(public readonly ?string $locale = null) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['locale' => ['nullable', 'string', 'max:35', Rule::in(Locales::supported())]];
    }
}
