<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated optional scope for management status. */
#[TypeScript]
final class SeoScopeQueryData extends SeoManagementInputData
{
    public function __construct(public readonly ?string $scope = null) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['scope' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/']];
    }
}
