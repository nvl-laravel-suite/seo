<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data\Mutations;

use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Tests\Fixtures\Data\DataTransform;
use Nvl\Seo\Tests\Fixtures\Data\SeoManagementInputData;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * Complete patch/replace contract for an owner's scoped SEO profile.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class SeoProfilePayload extends Data
{
    use DataTransform;

    /**
     * Return every accepted camel-case profile mutation field.
     *
     * @return list<string>
     */
    public static function fields(): array
    {
        return [
            'isIndexable',
            'isFollowable',
            'maxSnippet',
            'maxImagePreview',
            'maxVideoPreview',
            'sitemapIncluded',
            'sitemapPriority',
            'sitemapChangeFrequency',
            'metadata',
            'translations',
            'expectedRevision',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>|Optional  $translations
     * @param  array<string, mixed>|Optional|null  $metadata
     */
    public function __construct(
        public readonly bool|Optional $isIndexable = new Optional,
        public readonly bool|Optional $isFollowable = new Optional,
        public readonly int|Optional|null $maxSnippet = new Optional,
        public readonly string|Optional|null $maxImagePreview = new Optional,
        public readonly int|Optional|null $maxVideoPreview = new Optional,
        public readonly bool|Optional $sitemapIncluded = new Optional,
        public readonly string|Optional|null $sitemapPriority = new Optional,
        public readonly SitemapChangeFrequency|Optional|null $sitemapChangeFrequency = new Optional,
        public readonly array|Optional|null $metadata = new Optional,
        public readonly array|Optional $translations = new Optional,
        public readonly int|Optional|null $expectedRevision = new Optional,
    ) {}

    /**
     * Return validation rules for the complete SEO mutation boundary.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'isIndexable' => ['sometimes', 'boolean'],
            'isFollowable' => ['sometimes', 'boolean'],
            'maxSnippet' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'maxImagePreview' => ['sometimes', 'nullable', Rule::in(['none', 'standard', 'large'])],
            'maxVideoPreview' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'sitemapIncluded' => ['sometimes', 'boolean'],
            'sitemapPriority' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'sitemapChangeFrequency' => [
                'sometimes',
                'nullable',
                Rule::enum(SitemapChangeFrequency::class),
            ],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'translations' => ['sometimes', 'array', new SupportedLocaleMapRule],
            'translations.*' => [
                'array:'.implode(',', SeoTranslationPayload::fields()),
            ],
            'expectedRevision' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ...SeoTranslationPayload::scopedRules('translations.*.'),
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        SeoManagementInputData::rejectUnknownFields($validator, self::rules());
    }

    /**
     * Validate the stricter update boundary before creating the reusable mutation payload.
     *
     * @param  array<string, mixed>  $input
     */
    public static function validateForUpdate(array $input): self
    {
        $rules = array_replace(self::rules(), ['expectedRevision' => ['required', 'integer', 'min:1']]);
        $validator = ValidatorFacade::make($input, $rules);
        SeoManagementInputData::rejectUnknownFields($validator, $rules);

        return self::from($validator->validate());
    }
}
