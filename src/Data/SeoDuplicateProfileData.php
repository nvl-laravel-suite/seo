<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Nvl\Seo\Rules\OwnerIdentifier;
use Nvl\Seo\Support\SeoModelIdentifier;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated profile duplication target. */
#[TypeScript]
final class SeoDuplicateProfileData extends SeoManagementInputData
{
    public function __construct(
        public readonly string $ownerAlias,
        public readonly string|int $ownerId,
        public readonly ?string $scope = null,
        public readonly ?bool $copyPaths = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'ownerAlias' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
            'ownerId' => ['required', new OwnerIdentifier],
            'scope' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'copyPaths' => ['sometimes', 'boolean'],
        ];
    }

    public function normalizedOwnerId(): string
    {
        return SeoModelIdentifier::normalize($this->ownerId);
    }
}
