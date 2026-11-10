<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Enums\TwitterCard;
use Nvl\Seo\Rules\CanonicalUrl;
use Nvl\Seo\Rules\OwnerIdentifier;
use Nvl\Seo\Rules\ValidStructuredData;
use Nvl\Seo\Support\SeoScope;
use Nvl\Support\Facades\Locales;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;

/** Native validation boundary for the consumer's SEO management and sitemap requests. */
final class SeoManagementInput
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function profiles(array $input): array
    {
        return self::validate($input, [
            'scope' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'ownerAlias' => ['required_with:ownerId', 'nullable', 'string', 'max:120'],
            'ownerId' => ['nullable', new OwnerIdentifier],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:200'],
        ]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function store(array $input): array
    {
        $profileRules = self::profileRules();
        $data = self::validate($input, [
            ...self::ownerRules(),
            'profile' => ['required', 'array:'.implode(',', array_filter(array_keys($profileRules), static fn (string $field): bool => ! str_contains($field, '.')))],
            ...self::prefixedRules($profileRules, 'profile.'),
            'profile.expected_revision' => ['sometimes', 'nullable', 'integer', Rule::in([0])],
        ]);
        $data['profile'] = self::normalizeProfile([...$data['profile'], 'expected_revision' => 0]);

        return $data;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function update(array $input): array
    {
        return self::normalizeProfile(self::validate($input, [
            ...self::profileRules(),
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]));
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function duplicate(array $input): array
    {
        return self::validate($input, [...self::ownerRules(), 'copyPaths' => ['sometimes', 'boolean']]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function archive(array $input): array
    {
        return self::validate($input, [
            'archived' => ['required', 'boolean'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function revision(array $input): array
    {
        return self::validate($input, ['expected_revision' => ['required', 'integer', 'min:1']]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function preview(array $input): array
    {
        return self::validate($input, ['locale' => ['nullable', 'string', 'max:35', Rule::in(Locales::supported())]]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function status(array $input): array
    {
        return self::validate($input, ['scope' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/']]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sitemap(array $input): array
    {
        return ValidatorFacade::make($input, [
            'scope' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::in(SeoScope::publicSitemapScopes())],
        ])->validate();
    }

    /** @return array<string, list<mixed>> */
    private static function ownerRules(): array
    {
        return [
            'ownerAlias' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
            'ownerId' => ['required', new OwnerIdentifier],
            'scope' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private static function profileRules(): array
    {
        $translationRules = [
            'path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonicalUrl' => ['sometimes', 'nullable', 'string', 'max:2048', new CanonicalUrl],
            'imageUrl' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'imageReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imageAlt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openGraphTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openGraphDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twitterTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitterDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twitterCard' => ['sometimes', 'nullable', Rule::enum(TwitterCard::class)],
            'structuredData' => ['sometimes', 'nullable', 'array', new ValidStructuredData],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];

        return [
            'isIndexable' => ['sometimes', 'boolean'],
            'isFollowable' => ['sometimes', 'boolean'],
            'maxSnippet' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'maxImagePreview' => ['sometimes', 'nullable', Rule::in(['none', 'standard', 'large'])],
            'maxVideoPreview' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'sitemapIncluded' => ['sometimes', 'boolean'],
            'sitemapPriority' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'sitemapChangeFrequency' => ['sometimes', 'nullable', Rule::enum(SitemapChangeFrequency::class)],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'translations' => ['sometimes', 'array', new SupportedLocaleMapRule],
            'translations.*' => ['array:'.implode(',', array_keys($translationRules))],
            'expected_revision' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ...self::prefixedRules($translationRules, 'translations.*.'),
        ];
    }

    /** @param array<string, list<mixed>> $rules
     * @return array<string, list<mixed>>
     */
    private static function prefixedRules(array $rules, string $prefix): array
    {
        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixed[$prefix.$field] = $rule;
        }

        return $prefixed;
    }

    /** @param array<string, mixed> $input
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validate(array $input, array $rules): array
    {
        foreach (['perPage' => 'per_page', 'expectedRevision' => 'expected_revision'] as $alias => $field) {
            if (array_key_exists($field, $rules) && array_key_exists($alias, $input)) {
                if (! array_key_exists($field, $input)) {
                    $input[$field] = $input[$alias];
                }
                unset($input[$alias]);
            }
        }
        $validator = ValidatorFacade::make($input, $rules);
        $validator->after(static function (Validator $validator) use ($rules): void {
            $allowed = array_unique(array_map(static fn (string $field): string => Str::before($field, '.'), array_keys($rules)));
            foreach (array_keys($validator->getData()) as $field) {
                if (is_string($field) && ! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, "The {$field} field is not supported.");
                }
            }
        });
        $data = $validator->validate();
        foreach (['page', 'per_page', 'expected_revision'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        foreach (['archived', 'copyPaths'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (bool) $data[$field];
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalizeProfile(array $data): array
    {
        foreach (['isIndexable', 'isFollowable', 'sitemapIncluded'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (bool) $data[$field];
            }
        }
        foreach (['maxSnippet', 'maxVideoPreview', 'expected_revision'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (isset($data['sitemapPriority'])) {
            $data['sitemapPriority'] = (string) $data['sitemapPriority'];
        }

        return $data;
    }
}
