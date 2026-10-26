<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Illuminate\Validation\Rule;
use Nvl\Support\Facades\Locales;

/** Validated optional preview locale. */
final class SeoPreviewQueryData extends SeoManagementInputData
{
    public function __construct(public readonly ?string $locale = null) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['locale' => ['nullable', 'string', 'max:35', Rule::in(Locales::supported())]];
    }
}
