<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Illuminate\Validation\Rule;
use Nvl\Seo\Rules\OwnerIdentifier;
use Nvl\Seo\Support\SeoModelIdentifier;
use Nvl\Seo\Tests\Fixtures\Data\Mutations\SeoProfilePayload;

/** Validated new SEO profile and registered owner identity. */
final class SeoStoreProfileData extends SeoManagementInputData
{
    /** @param array<string, mixed> $profile */
    public function __construct(
        public readonly string $ownerAlias,
        public readonly string|int $ownerId,
        public readonly array $profile,
        public readonly ?string $scope = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'ownerAlias' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
            'ownerId' => ['required', new OwnerIdentifier],
            'scope' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'profile' => ['required', 'array:'.implode(',', SeoProfilePayload::fields())],
            ...SeoProfilePayload::scopedRules('profile.'),
            'profile.expected_revision' => ['sometimes', 'nullable', 'integer', Rule::in([0])],
        ];
    }

    public function normalizedOwnerId(): string
    {
        return SeoModelIdentifier::normalize($this->ownerId);
    }

    public function payload(): SeoProfilePayload
    {
        return SeoProfilePayload::from([...$this->profile, 'expected_revision' => 0]);
    }
}
