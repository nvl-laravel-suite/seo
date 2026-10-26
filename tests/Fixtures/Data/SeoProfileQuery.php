<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nvl\Seo\Rules\OwnerIdentifier;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Allowlisted profile management query.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
final class SeoProfileQuery extends Data
{
    public function __construct(
        public readonly ?string $scope = null,
        public readonly ?string $status = null,
        public readonly ?string $ownerAlias = null,
        public readonly int $page = 1,
        #[MapInputName('per_page')] #[MapOutputName('per_page')] public readonly int $perPage = 50,
        public readonly string|int|null $ownerId = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'scope' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'ownerAlias' => ['required_with:ownerId', 'nullable', 'string', 'max:120'],
            'ownerId' => ['nullable', new OwnerIdentifier],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:200'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        SeoManagementInputData::rejectUnknownFields($validator, self::rules());
    }
}
