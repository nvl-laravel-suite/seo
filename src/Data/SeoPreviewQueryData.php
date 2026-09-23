<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Illuminate\Validation\Rule;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated optional preview locale. */
#[TypeScript]
final class SeoPreviewQueryData extends SeoManagementInputData
{
    public function __construct(public readonly ?string $locale = null) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $configured = config('translatable.locales', ['en']);
        $locales = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : ['en'];

        return ['locale' => ['nullable', 'string', 'max:35', Rule::in($locales)]];
    }
}
