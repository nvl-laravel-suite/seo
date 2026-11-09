# Public API: nvl/seo

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver`
- `Nvl\Seo\Contracts\OwnershipSafeSitemapSource`
- `Nvl\Seo\Contracts\SeoAuthorization`
- `Nvl\Seo\Contracts\SeoImageResolver`
- `Nvl\Seo\Contracts\SeoImportSource`
- `Nvl\Seo\Contracts\SitemapArtifactStore`
- `Nvl\Seo\Contracts\SitemapSource`
- `Nvl\Seo\Contracts\StructuredDataProvider`
- `Nvl\Seo\Database\Factories\SeoProfileFactory`
- `Nvl\Seo\Database\Factories\SeoProfileTranslationFactory`
- `Nvl\Seo\Database\Factories\SeoRedirectFactory`
- `Nvl\Seo\Enums\SeoAbility`
- `Nvl\Seo\Enums\SeoResponseCode`
- `Nvl\Seo\Enums\SitemapChangeFrequency`
- `Nvl\Seo\Enums\StructuredDataType`
- `Nvl\Seo\Enums\TwitterCard`
- `Nvl\Seo\Events\SeoProfileChanged`
- `Nvl\Seo\Exceptions\InvalidSeoMutationException`
- `Nvl\Seo\Exceptions\SeoException`
- `Nvl\Seo\Exceptions\SeoPathConflictException`
- `Nvl\Seo\Exceptions\SeoRedirectLoopException`
- `Nvl\Seo\Exceptions\StaleSeoProfileException`
- `Nvl\Seo\Exceptions\StaleSeoRedirectException`
- `Nvl\Seo\Models\SeoProfile`
- `Nvl\Seo\Models\SeoProfileTranslation`
- `Nvl\Seo\Models\SeoRedirect`
- `Nvl\Seo\Rules\UniqueSeoPath`
- `Nvl\Seo\Services\RobotsGenerator`
- `Nvl\Seo\Services\SeoHeadRenderer`
- `Nvl\Seo\Services\SeoModelRegistry`
- `Nvl\Seo\Services\SeoOwnerRegistry`
- `Nvl\Seo\Services\SeoProfiles`
- `Nvl\Seo\Services\SeoReader`
- `Nvl\Seo\Services\SeoRedirectChain`
- `Nvl\Seo\Services\SeoRedirects`
- `Nvl\Seo\Services\SeoRouteResolver`
- `Nvl\Seo\Services\SitemapGenerator`
- `Nvl\Seo\Services\SitemapRegistry`
- `Nvl\Seo\Services\StructuredDataBuilder`
- `Nvl\Seo\Services\StructuredDataRegistry`
- `Nvl\Seo\Services\StructuredDataResolver`
- `Nvl\Seo\Support\SeoAuthorizationContext`
- `Nvl\Seo\Support\SeoImageContext`
- `Nvl\Seo\Support\SeoPath`
- `Nvl\Seo\Traits\HasSeo`
- `Nvl\Seo\ValueObjects\SeoImage`
- `Nvl\Seo\ValueObjects\SitemapEntry`
- `Nvl\Seo\ValueObjects\StructuredDataNode`

## Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver

```text
interface Nvl\Seo\Contracts\OwnershipSafeSeoImageResolver extends Nvl\Seo\Contracts\SeoImageResolver
public abstract function assertOwnershipSafe(Nvl\Seo\Support\SeoImageContext $context): void
public abstract function resolve(Nvl\Seo\Support\SeoImageContext $context): ?Nvl\Seo\ValueObjects\SeoImage
```

## Nvl\Seo\Contracts\OwnershipSafeSitemapSource

```text
interface Nvl\Seo\Contracts\OwnershipSafeSitemapSource extends Nvl\Seo\Contracts\SitemapSource
public abstract function entries(string $scope): iterable
@return iterable<SitemapEntry>
resolved @return iterable<Nvl\Seo\ValueObjects\SitemapEntry>
public abstract function ownershipResources(): array
@return list<string>
```

## Nvl\Seo\Contracts\SeoAuthorization

```text
interface Nvl\Seo\Contracts\SeoAuthorization
public abstract function authorize(Nvl\Seo\Support\SeoAuthorizationContext $context): void
```

## Nvl\Seo\Contracts\SeoImageResolver

```text
interface Nvl\Seo\Contracts\SeoImageResolver
public abstract function resolve(Nvl\Seo\Support\SeoImageContext $context): ?Nvl\Seo\ValueObjects\SeoImage
```

## Nvl\Seo\Contracts\SeoImportSource

```text
interface Nvl\Seo\Contracts\SeoImportSource
public abstract function page(?string $cursor, int $limit): array
@return array{items: list<array{ownerAlias: string, ownerId: string, scope: (string | null), profile: ProfileMutation}>, nextCursor: (string | null)}
resolved @return array{items: list<array{ownerAlias: string, ownerId: string, scope: (string | null), profile: array{isIndexable?: bool, isFollowable?: bool, maxSnippet?: (int | null), maxImagePreview?: (string | null), maxVideoPreview?: (int | null), sitemapIncluded?: bool, sitemapPriority?: (string | null), sitemapChangeFrequency?: (Nvl\Seo\Enums\SitemapChangeFrequency | string | null), metadata?: (array<string, mixed> | null), translations?: array<string, array<string, mixed>>, expected_revision?: (int | null)}}>, nextCursor: (string | null)}
```

## Nvl\Seo\Contracts\SitemapArtifactStore

```text
interface Nvl\Seo\Contracts\SitemapArtifactStore
public abstract function deleteNamespace(string $namespace): void
public abstract function read(string $namespace, string $artifact): ?string
public abstract function write(string $namespace, string $artifact, string $contents): void
```

## Nvl\Seo\Contracts\SitemapSource

```text
interface Nvl\Seo\Contracts\SitemapSource
public abstract function entries(string $scope): iterable
@return iterable<SitemapEntry>
resolved @return iterable<Nvl\Seo\ValueObjects\SitemapEntry>
```

## Nvl\Seo\Contracts\StructuredDataProvider

```text
interface Nvl\Seo\Contracts\StructuredDataProvider
public abstract function provide(Illuminate\Database\Eloquent\Model $resource, array $context): iterable
@param StructuredContext $context
resolved @param array{resourceType: string, resourceId: string, profileId: string, locale: string, scope: string, canonicalUrl: (string | null), title: (string | null), description: (string | null), imageUrl: (string | null), siteName: string, siteUrl: string} $context
@return iterable<StructuredDataNode>
resolved @return iterable<Nvl\Seo\ValueObjects\StructuredDataNode>
```

## Nvl\Seo\Database\Factories\SeoProfileFactory

```text
final class Nvl\Seo\Database\Factories\SeoProfileFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<SeoProfile>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Seo\Models\SeoProfile>
public function definition(): array
@return array<model-property<SeoProfile>, mixed>
resolved @return array<model-property<Nvl\Seo\Models\SeoProfile>, mixed>
public function forOwner(Illuminate\Database\Eloquent\Model $owner): static
public function modelName(): string
```

## Nvl\Seo\Database\Factories\SeoProfileTranslationFactory

```text
final class Nvl\Seo\Database\Factories\SeoProfileTranslationFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<SeoProfileTranslation>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Seo\Models\SeoProfileTranslation>
public function definition(): array
@return array<model-property<SeoProfileTranslation>, mixed>
resolved @return array<model-property<Nvl\Seo\Models\SeoProfileTranslation>, mixed>
public function forProfile(Nvl\Seo\Models\SeoProfile $parent): static
```

## Nvl\Seo\Database\Factories\SeoRedirectFactory

```text
final class Nvl\Seo\Database\Factories\SeoRedirectFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<SeoRedirect>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Seo\Models\SeoRedirect>
public function definition(): array
@return array<model-property<SeoRedirect>, mixed>
resolved @return array<model-property<Nvl\Seo\Models\SeoRedirect>, mixed>
public function modelName(): string
```

## Nvl\Seo\Enums\SeoAbility

```text
enum Nvl\Seo\Enums\SeoAbility implements BackedEnum, UnitEnum: string
public const Nvl\Seo\Enums\SeoAbility Archive=Nvl\Seo\Enums\SeoAbility::Archive="archive"
public const Nvl\Seo\Enums\SeoAbility Create=Nvl\Seo\Enums\SeoAbility::Create="create"
public const Nvl\Seo\Enums\SeoAbility Delete=Nvl\Seo\Enums\SeoAbility::Delete="delete"
public const Nvl\Seo\Enums\SeoAbility Duplicate=Nvl\Seo\Enums\SeoAbility::Duplicate="duplicate"
public const Nvl\Seo\Enums\SeoAbility Import=Nvl\Seo\Enums\SeoAbility::Import="import"
public const Nvl\Seo\Enums\SeoAbility List=Nvl\Seo\Enums\SeoAbility::List="list"
public const Nvl\Seo\Enums\SeoAbility ManageRedirects=Nvl\Seo\Enums\SeoAbility::ManageRedirects="manage_redirects"
public const Nvl\Seo\Enums\SeoAbility Preview=Nvl\Seo\Enums\SeoAbility::Preview="preview"
public const Nvl\Seo\Enums\SeoAbility Resolve=Nvl\Seo\Enums\SeoAbility::Resolve="resolve"
public const Nvl\Seo\Enums\SeoAbility Update=Nvl\Seo\Enums\SeoAbility::Update="update"
public const Nvl\Seo\Enums\SeoAbility View=Nvl\Seo\Enums\SeoAbility::View="view"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Seo\Enums\SeoResponseCode

```text
enum Nvl\Seo\Enums\SeoResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Seo\Enums\SeoResponseCode InvalidSeoMutation=Nvl\Seo\Enums\SeoResponseCode::InvalidSeoMutation="invalid_seo_mutation"
public const Nvl\Seo\Enums\SeoResponseCode OperationFailed=Nvl\Seo\Enums\SeoResponseCode::OperationFailed="operation_failed"
public const Nvl\Seo\Enums\SeoResponseCode SeoPathConflict=Nvl\Seo\Enums\SeoResponseCode::SeoPathConflict="seo_path_conflict"
public const Nvl\Seo\Enums\SeoResponseCode SeoRedirectLoop=Nvl\Seo\Enums\SeoResponseCode::SeoRedirectLoop="seo_redirect_loop"
public const Nvl\Seo\Enums\SeoResponseCode StaleSeoProfile=Nvl\Seo\Enums\SeoResponseCode::StaleSeoProfile="stale_seo_profile"
public const Nvl\Seo\Enums\SeoResponseCode StaleSeoRedirect=Nvl\Seo\Enums\SeoResponseCode::StaleSeoRedirect="stale_seo_redirect"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Seo\Enums\SitemapChangeFrequency

```text
enum Nvl\Seo\Enums\SitemapChangeFrequency implements BackedEnum, UnitEnum: string
public const Nvl\Seo\Enums\SitemapChangeFrequency Always=Nvl\Seo\Enums\SitemapChangeFrequency::Always="always"
public const Nvl\Seo\Enums\SitemapChangeFrequency Daily=Nvl\Seo\Enums\SitemapChangeFrequency::Daily="daily"
public const Nvl\Seo\Enums\SitemapChangeFrequency Hourly=Nvl\Seo\Enums\SitemapChangeFrequency::Hourly="hourly"
public const Nvl\Seo\Enums\SitemapChangeFrequency Monthly=Nvl\Seo\Enums\SitemapChangeFrequency::Monthly="monthly"
public const Nvl\Seo\Enums\SitemapChangeFrequency Never=Nvl\Seo\Enums\SitemapChangeFrequency::Never="never"
public const Nvl\Seo\Enums\SitemapChangeFrequency Weekly=Nvl\Seo\Enums\SitemapChangeFrequency::Weekly="weekly"
public const Nvl\Seo\Enums\SitemapChangeFrequency Yearly=Nvl\Seo\Enums\SitemapChangeFrequency::Yearly="yearly"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Seo\Enums\StructuredDataType

```text
enum Nvl\Seo\Enums\StructuredDataType implements BackedEnum, UnitEnum: string
public const Nvl\Seo\Enums\StructuredDataType Article=Nvl\Seo\Enums\StructuredDataType::Article="Article"
public const Nvl\Seo\Enums\StructuredDataType BlogPosting=Nvl\Seo\Enums\StructuredDataType::BlogPosting="BlogPosting"
public const Nvl\Seo\Enums\StructuredDataType BreadcrumbList=Nvl\Seo\Enums\StructuredDataType::BreadcrumbList="BreadcrumbList"
public const Nvl\Seo\Enums\StructuredDataType CollectionPage=Nvl\Seo\Enums\StructuredDataType::CollectionPage="CollectionPage"
public const Nvl\Seo\Enums\StructuredDataType Event=Nvl\Seo\Enums\StructuredDataType::Event="Event"
public const Nvl\Seo\Enums\StructuredDataType FAQPage=Nvl\Seo\Enums\StructuredDataType::FAQPage="FAQPage"
public const Nvl\Seo\Enums\StructuredDataType ImageObject=Nvl\Seo\Enums\StructuredDataType::ImageObject="ImageObject"
public const Nvl\Seo\Enums\StructuredDataType LocalBusiness=Nvl\Seo\Enums\StructuredDataType::LocalBusiness="LocalBusiness"
public const Nvl\Seo\Enums\StructuredDataType NewsArticle=Nvl\Seo\Enums\StructuredDataType::NewsArticle="NewsArticle"
public const Nvl\Seo\Enums\StructuredDataType Organization=Nvl\Seo\Enums\StructuredDataType::Organization="Organization"
public const Nvl\Seo\Enums\StructuredDataType Person=Nvl\Seo\Enums\StructuredDataType::Person="Person"
public const Nvl\Seo\Enums\StructuredDataType Product=Nvl\Seo\Enums\StructuredDataType::Product="Product"
public const Nvl\Seo\Enums\StructuredDataType ProfilePage=Nvl\Seo\Enums\StructuredDataType::ProfilePage="ProfilePage"
public const Nvl\Seo\Enums\StructuredDataType VideoObject=Nvl\Seo\Enums\StructuredDataType::VideoObject="VideoObject"
public const Nvl\Seo\Enums\StructuredDataType WebPage=Nvl\Seo\Enums\StructuredDataType::WebPage="WebPage"
public const Nvl\Seo\Enums\StructuredDataType WebSite=Nvl\Seo\Enums\StructuredDataType::WebSite="WebSite"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Seo\Enums\TwitterCard

```text
enum Nvl\Seo\Enums\TwitterCard implements BackedEnum, UnitEnum: string
public const Nvl\Seo\Enums\TwitterCard App=Nvl\Seo\Enums\TwitterCard::App="app"
public const Nvl\Seo\Enums\TwitterCard Player=Nvl\Seo\Enums\TwitterCard::Player="player"
public const Nvl\Seo\Enums\TwitterCard Summary=Nvl\Seo\Enums\TwitterCard::Summary="summary"
public const Nvl\Seo\Enums\TwitterCard SummaryLargeImage=Nvl\Seo\Enums\TwitterCard::SummaryLargeImage="summary_large_image"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Seo\Events\SeoProfileChanged

```text
final class Nvl\Seo\Events\SeoProfileChanged implements Nvl\Support\Contracts\DomainEvent
public function __construct(string $profileId, string $scope, string $operation, int $schemaVersion=1): mixed
public function schemaVersion(): int
public readonly string $operation
public readonly string $profileId
public readonly int $schemaVersion
public readonly string $scope
```

## Nvl\Seo\Exceptions\InvalidSeoMutationException

```text
final class Nvl\Seo\Exceptions\InvalidSeoMutationException extends Nvl\Seo\Exceptions\SeoException
public static function because(string $message, array $errors=[], ?Throwable $previous=null): self
@param array<string, list<string>> $errors
public static function forField(string $field, string $message, ?Throwable $previous=null): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Exceptions\SeoException

```text
abstract class Nvl\Seo\Exceptions\SeoException extends RuntimeException implements Illuminate\Contracts\Debug\ShouldntReport, Nvl\Support\Contracts\RespondableException
public function __construct(string $message, ?Throwable $previous=null): mixed
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public abstract function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Exceptions\SeoPathConflictException

```text
final class Nvl\Seo\Exceptions\SeoPathConflictException extends Nvl\Seo\Exceptions\SeoException
public function __construct(string $message, ?Throwable $previous=null): mixed
public static function concurrent(string $scope, ?Throwable $previous=null): self
public static function forRoute(string $scope, string $locale, string $path, string $profileId): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Exceptions\SeoRedirectLoopException

```text
final class Nvl\Seo\Exceptions\SeoRedirectLoopException extends Nvl\Seo\Exceptions\SeoException
public function __construct(string $message, ?Throwable $previous=null): mixed
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Exceptions\StaleSeoProfileException

```text
final class Nvl\Seo\Exceptions\StaleSeoProfileException extends Nvl\Seo\Exceptions\SeoException
public function __construct(string $message, ?Throwable $previous=null): mixed
public static function forProfile(string $id, ?Throwable $previous=null): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Exceptions\StaleSeoRedirectException

```text
final class Nvl\Seo\Exceptions\StaleSeoRedirectException extends Nvl\Seo\Exceptions\SeoException
public function __construct(string $message, ?Throwable $previous=null): mixed
public static function forRedirect(string $id, ?Throwable $previous=null): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Seo\Models\SeoProfile

```text
class Nvl\Seo\Models\SeoProfile extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable, Nvl\Translatable\Contracts\TranslatableModel
public const string TENANT_RESOURCE="seo.profiles"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getAllTranslations(): Illuminate\Database\Eloquent\Collection
@return Collection<int, Model>
resolved @return Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Model>
public function getAvailableLocales(): array
@return list<string>
public function getConnectionName(): ?string
public function getCurrentLocale(): string
public function getRelationValue(mixed $key): mixed
@param string $key
public function getTable(): string
public function getTranslatedAttributes(?string $locale=null): array
@return array<string, mixed>
public function getTranslation(?string $locale=null, bool $withFallback=true): ?Illuminate\Database\Eloquent\Model
public function hasTranslation(?string $locale=null): bool
public function isTranslatableAttribute(string $key): bool
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function resolveTranslation(string $field, ?string $locale=null): Nvl\Translatable\TranslationResolution
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
public function scopeForOwner(Illuminate\Database\Eloquent\Builder $query, Illuminate\Database\Eloquent\Model $owner, ?string $scope=null): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
public function scopeOrderByTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, string $direction="asc", ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function scopeWhereTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, mixed $operator, mixed $value=\Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function scopeWhereTranslationNotNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function scopeWhereTranslationNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function scopeWithAllTranslations(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function scopeWithResolvedTranslations(Illuminate\Database\Eloquent\Builder $query, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile>
public function seoable(): Illuminate\Database\Eloquent\Relations\MorphTo
@return MorphTo<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\MorphTo<Illuminate\Database\Eloquent\Model, $this>
public function setLocale(string $locale): self
public function translated(string $field, ?string $locale=null): mixed
public function translation(?string $locale=null): Illuminate\Database\Eloquent\Relations\HasOne
@return HasOne<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasOne<Illuminate\Database\Eloquent\Model, $this>
public function translationDefinition(): Nvl\Translatable\RelatedTranslationDefinition
public function translationResourceKey(): int|string
public function translations(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Illuminate\Database\Eloquent\Model, $this>
@method static Builder<static> forOwner(Model $owner, ?string $scope = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> forOwner(Illuminate\Database\Eloquent\Model $owner, ?string $scope = null)
@method static Builder<static> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
@method static Builder<static> whereTranslated(string $field, mixed $operator, mixed $value = TranslationComparisonValue::Omitted, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> whereTranslated(string $field, mixed $operator, mixed $value = Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale = null)
@method static Builder<static> whereTranslationNotNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> whereTranslationNotNull(string $field, ?string $locale = null)
@method static Builder<static> whereTranslationNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> whereTranslationNull(string $field, ?string $locale = null)
@method static Builder<static> withAllTranslations()
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> withAllTranslations()
@method static Builder<static> withResolvedTranslations(?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfile> withResolvedTranslations(?string $locale = null)
@property (Carbon | null) $archived_at
resolved @property (Carbon\Carbon | null) $archived_at
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property string $id
@property bool $is_followable
@property bool $is_indexable
@property (string | null) $max_image_preview
@property (int | null) $max_snippet
@property (int | null) $max_video_preview
@property (array<string, mixed> | null) $metadata
@property int $revision
@property string $scope
@property-read Model $seoable
resolved @property-read Illuminate\Database\Eloquent\Model $seoable
@property string $seoable_id
@property string $seoable_type
@property (SitemapChangeFrequency | null) $sitemap_change_frequency
resolved @property (Nvl\Seo\Enums\SitemapChangeFrequency | null) $sitemap_change_frequency
@property bool $sitemap_included
@property (string | null) $sitemap_priority
@property string $status
@property-read Collection<int, SeoProfileTranslation> $translations
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Seo\Models\SeoProfileTranslation> $translations
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
```

## Nvl\Seo\Models\SeoProfileTranslation

```text
final class Nvl\Seo\Models\SeoProfileTranslation extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string TENANT_RESOURCE="seo.translations"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoProfileTranslation>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function profile(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<SeoProfile, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Seo\Models\SeoProfile, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property (string | null) $canonical_url
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property (string | null) $description
@property string $id
@property (string | null) $image_alt
@property (string | null) $image_reference
@property (string | null) $image_url
@property string $locale
@property (array<string, mixed> | null) $metadata
@property (string | null) $open_graph_description
@property (string | null) $open_graph_title
@property (string | null) $path
@property (string | null) $path_hash
@property-read SeoProfile $profile
resolved @property-read Nvl\Seo\Models\SeoProfile $profile
@property string $scope
@property string $seo_profile_id
@property (array<array-key, mixed> | null) $structured_data
@property (string | null) $title
@property (TwitterCard | null) $twitter_card
resolved @property (Nvl\Seo\Enums\TwitterCard | null) $twitter_card
@property (string | null) $twitter_description
@property (string | null) $twitter_title
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
```

## Nvl\Seo\Models\SeoRedirect

```text
class Nvl\Seo\Models\SeoRedirect extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string TENANT_RESOURCE="seo.redirects"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Seo\Models\SeoRedirect>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
public static function sourceHash(string $scope, ?string $locale, string $source): string
public static function sourceHashForPartition(string $tenantId, string $scope, ?string $locale, string $source): string
@property (Carbon | null) $deleted_at
resolved @property (Illuminate\Support\Carbon | null) $deleted_at
@property (Carbon | null) $expires_at
resolved @property (Illuminate\Support\Carbon | null) $expires_at
@property int $hit_count
@property string $id
@property bool $is_active
@property (Carbon | null) $last_hit_at
resolved @property (Illuminate\Support\Carbon | null) $last_hit_at
@property (string | null) $locale
@property (array<string, mixed> | null) $metadata
@property int $revision
@property string $scope
@property string $source_hash
@property string $source_path
@property int $status_code
@property string $target
```

## Nvl\Seo\Rules\UniqueSeoPath

```text
final readonly class Nvl\Seo\Rules\UniqueSeoPath implements Illuminate\Contracts\Validation\ValidationRule
public function __construct(string $locale, ?string $scope=null, ?string $ignoreProfileId=null): mixed
public function validate(string $attribute, mixed $value, Closure $fail): void
@param Closure(string, ?string=): mixed $fail
```

## Nvl\Seo\Services\RobotsGenerator

```text
final readonly class Nvl\Seo\Services\RobotsGenerator
public function __construct(Nvl\Seo\Services\AbsoluteUrl $urls): mixed
public function generate(): string
```

## Nvl\Seo\Services\SeoHeadRenderer

```text
final class Nvl\Seo\Services\SeoHeadRenderer
public function __construct(Nvl\Seo\Services\SeoReader $reader): mixed
public function render(Illuminate\Database\Eloquent\Model $owner, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, ?string $locale=null, ?string $scope=null): string
public function renderResolved(array $seo): string
@param array{locale: string, title: (string | null), description: (string | null), canonicalUrl: (string | null), robots: string, alternates: array<string, string>, openGraph: array<string, string>, openGraphLocales: list<string>, twitter: array<string, string>, structuredData: list<array<string, mixed>>} $seo
@throws JsonException
```

## Nvl\Seo\Services\SeoModelRegistry

```text
final class Nvl\Seo\Services\SeoModelRegistry
public function __construct(Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\OwnerRegistry $identities): mixed
public static function profileClass(): string
@return class-string<SeoProfile>
resolved @return class-string<Nvl\Seo\Models\SeoProfile>
public static function redirectClass(): string
@return class-string<SeoRedirect>
resolved @return class-string<Nvl\Seo\Models\SeoRedirect>
```

## Nvl\Seo\Services\SeoOwnerRegistry

```text
final class Nvl\Seo\Services\SeoOwnerRegistry
public function __construct(Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\Ownership\Contracts\OwnershipBoundary $tenancy, Nvl\Support\OwnerRegistry $identities, Nvl\Support\Ownership\Services\OwnershipResourceRegistry $ownershipResources): mixed
public function aliasFor(Illuminate\Database\Eloquent\Model $model): string
public function aliasForMorphType(string $morphType): string
public function configured(): array
@return array<string, class-string<Model>>
resolved @return array<string, class-string<Illuminate\Database\Eloquent\Model>>
public function modelClass(string $alias): string
@return class-string<Model>
resolved @return class-string<Illuminate\Database\Eloquent\Model>
public function morphTypeForAlias(string $alias): string
public function register(string $alias, string $model): void
public function resolve(string $alias, string|int $id): Illuminate\Database\Eloquent\Model
```

## Nvl\Seo\Services\SeoProfiles

```text
final readonly class Nvl\Seo\Services\SeoProfiles
public function __construct(Nvl\Translatable\Services\RelatedTranslationStore $translations, Nvl\Seo\Services\SeoTranslationNormalizer $translationNormalizer, Nvl\Seo\Services\SeoPathConflictResolver $pathConflicts, Nvl\Seo\Services\SeoMutationValidator $validator, Nvl\Seo\Services\SitemapCache $sitemapCache, Nvl\Seo\Services\SeoOwnerRegistry $owners, Nvl\Support\Events\DomainEventDispatcher $domainEvents, Nvl\Seo\Contracts\SeoAuthorization $authorization): mixed
public function archive(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Models\SeoProfile|string $profile, bool $archived, int $expectedRevision): Nvl\Seo\Models\SeoProfile
public function delete(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Models\SeoProfile|string $profile, ?int $expectedRevision=null): bool
public function duplicate(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Models\SeoProfile|string $source, Illuminate\Database\Eloquent\Model $target, ?string $scope=null, bool $copyPaths=false): Nvl\Seo\Models\SeoProfile
public function import(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Contracts\SeoImportSource $source, ?string $cursor=null, int $limit=100): array
@return array{processed: int, nextCursor: (string | null)}
public function status(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, ?string $scope=null): array
@return array{active: int, archived: int, total: int}
public function sync(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Illuminate\Database\Eloquent\Model $owner, array $data, ?string $scope=null, Nvl\Translatable\Enums\TranslationSyncMode $translationMode=\Nvl\Translatable\Enums\TranslationSyncMode::Patch): Nvl\Seo\Models\SeoProfile
@param ProfileMutation $data
resolved @param array{isIndexable?: bool, isFollowable?: bool, maxSnippet?: (int | null), maxImagePreview?: (string | null), maxVideoPreview?: (int | null), sitemapIncluded?: bool, sitemapPriority?: (string | null), sitemapChangeFrequency?: (Nvl\Seo\Enums\SitemapChangeFrequency | string | null), metadata?: (array<string, mixed> | null), translations?: array<string, array<string, mixed>>, expected_revision?: (int | null)} $data
```

## Nvl\Seo\Services\SeoReader

```text
final readonly class Nvl\Seo\Services\SeoReader
public function __construct(Nvl\Seo\Contracts\SeoAuthorization $authorization, Nvl\Seo\Services\SeoOwnerRegistry $owners, Nvl\Translatable\Services\ContentLocale $contentLocale, Nvl\Seo\Contracts\SeoImageResolver $images, Nvl\Seo\Services\AbsoluteUrl $urls, Nvl\Seo\Services\StructuredDataResolver $structuredData, Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\Ownership\Contracts\OwnershipBoundary $tenancy, Nvl\Support\Contracts\LocaleCatalog $locales, Nvl\Support\Ownership\Services\OwnershipResourceRegistry $ownershipResources): mixed
public function forOwner(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Illuminate\Database\Eloquent\Model $owner, ?string $scope=null): ?Nvl\Seo\Models\SeoProfile
public function forOwners(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, iterable $owners, ?string $scope=null): array
@param iterable<array-key, Model> $owners
resolved @param iterable<array-key, Illuminate\Database\Eloquent\Model> $owners
@return list<(SeoProfile | null)>
resolved @return list<(Nvl\Seo\Models\SeoProfile | null)>
public function get(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Models\SeoProfile|string $profile): Nvl\Seo\Models\SeoProfile
public function paginate(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, array $query=[]): Illuminate\Contracts\Pagination\LengthAwarePaginator
@param array{scope?: (string | null), ownerAlias?: (string | null), ownerId?: (int | string | null), status?: (string | null), per_page?: int, page?: int} $query
@return LengthAwarePaginator<int, SeoProfile>
resolved @return Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Nvl\Seo\Models\SeoProfile>
public function resolve(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, Illuminate\Database\Eloquent\Model|Nvl\Seo\Models\SeoProfile $owner, ?string $locale=null, ?string $scope=null): array
@return array{locale: string, title: (string | null), description: (string | null), canonicalUrl: (string | null), robots: string, alternates: array<string, string>, openGraph: array<string, string>, openGraphLocales: list<string>, twitter: array<string, string>, structuredData: list<array<string, mixed>>}
public function revisions(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Illuminate\Database\Eloquent\Model $owner, ?string $scope=null): array
@return array{ownerAlias: string, ownerId: string, scope: string, profileId: (string | null), revision: int}
```

## Nvl\Seo\Services\SeoRedirectChain

```text
final class Nvl\Seo\Services\SeoRedirectChain
public function __construct(Nvl\Support\Contracts\LocaleCatalog $locales, Nvl\Seo\Services\SeoRedirectLookup $redirects, Nvl\Seo\Services\AbsoluteUrl $urls): mixed
public function assertAcyclic(string $scope, string $source): void
public function flatten(string $scope, ?string $locale, string $source, string $target, ?string $ignoreId=null): string
```

## Nvl\Seo\Services\SeoRedirects

```text
final readonly class Nvl\Seo\Services\SeoRedirects
public function __construct(Nvl\Seo\Services\SeoRedirectChain $chains, Nvl\Support\Contracts\LocaleCatalog $locales, Nvl\Seo\Services\SeoMutationValidator $validator, Nvl\Seo\Contracts\SeoAuthorization $authorization, Nvl\Seo\Services\SeoRedirectLookup $redirects): mixed
public function find(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, string $source, ?string $scope=null, ?string $locale=null): ?Nvl\Seo\Models\SeoRedirect
public function prune(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, int $retentionDays=30): int
public function resolve(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, string $source, ?string $locale=null, ?string $scope=null): ?array
@return (array{id: string, source: string, target: string, statusCode: int} | null)
public function sync(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, Nvl\Seo\Models\SeoRedirect|string|null $redirect, array $data, ?string $scope=null): Nvl\Seo\Models\SeoRedirect
@param array{sourcePath: string, target: string, locale?: (string | null), statusCode?: int, isActive?: bool, expiresAt?: (string | null), metadata?: (array<string, mixed> | null), expected_revision?: (int | null)} $data
```

## Nvl\Seo\Services\SeoRouteResolver

```text
final class Nvl\Seo\Services\SeoRouteResolver
public function resolve(string $path, string $locale, ?string $scope=null): ?Nvl\Seo\Models\SeoProfile
```

## Nvl\Seo\Services\SitemapGenerator

```text
final readonly class Nvl\Seo\Services\SitemapGenerator
public function __construct(Nvl\Seo\Services\SitemapRegistry $sources, Nvl\Seo\Services\SitemapCache $cacheKeys, Illuminate\Contracts\Cache\Repository $cache, Nvl\Seo\Contracts\SitemapArtifactStore $artifacts, Nvl\Seo\Services\AbsoluteUrl $urls, ?Nvl\Seo\Services\SitemapLocationPolicy $locations=null): mixed
public function chunkCount(?string $scope=null): int
public function generate(?string $scope=null): string
public function generateChunk(int $chunk, ?string $scope=null): string
```

## Nvl\Seo\Services\SitemapRegistry

```text
final class Nvl\Seo\Services\SitemapRegistry
public function __construct(Illuminate\Container\Container $container): mixed
public function all(): array
@return list<SitemapSource>
resolved @return list<Nvl\Seo\Contracts\SitemapSource>
public function ownedProfileTypes(): array
@return list<string>
public function register(Nvl\Seo\Contracts\SitemapSource $source, ?string $key=null, array $ownerTypes=[]): self
@param list<string> $ownerTypes
public function registerType(string $sourceClass, ?string $key=null, array $ownerTypes=[]): self
@param list<class-string<Model>> $ownerTypes
resolved @param list<class-string<Illuminate\Database\Eloquent\Model>> $ownerTypes
```

## Nvl\Seo\Services\StructuredDataBuilder

```text
final class Nvl\Seo\Services\StructuredDataBuilder
public function breadcrumbs(array $items): array
@param list<array{name: string, url: string}> $items
@return array<string, mixed>
public function graph(array $nodes): array
@param list<(array<string, mixed> | StructuredDataNode)> $nodes
resolved @param list<(array<string, mixed> | Nvl\Seo\ValueObjects\StructuredDataNode)> $nodes
@return array<string, mixed>
public function node(string|Nvl\Seo\Enums\StructuredDataType $type, array $properties=[], ?string $id=null): Nvl\Seo\ValueObjects\StructuredDataNode
@param array<string, mixed> $properties
public function reference(string $id): array
@return array{'@id': string}
public function schema(string|Nvl\Seo\Enums\StructuredDataType $type, array $properties): array
@param array<string, mixed> $properties
@return array<string, mixed>
```

## Nvl\Seo\Services\StructuredDataRegistry

```text
final class Nvl\Seo\Services\StructuredDataRegistry
public function keys(): array
@return list<string>
public function register(string $key, string $resourceClass, Nvl\Seo\Contracts\StructuredDataProvider $provider, int $priority=0): void
public function resolve(Illuminate\Database\Eloquent\Model $resource, array $context): array
@param StructuredContext $context
resolved @param array{resourceType: string, resourceId: string, profileId: string, locale: string, scope: string, canonicalUrl: (string | null), title: (string | null), description: (string | null), imageUrl: (string | null), siteName: string, siteUrl: string} $context
@return list<StructuredDataNode>
resolved @return list<Nvl\Seo\ValueObjects\StructuredDataNode>
```

## Nvl\Seo\Services\StructuredDataResolver

```text
final readonly class Nvl\Seo\Services\StructuredDataResolver
public function __construct(Nvl\Seo\Services\StructuredDataRegistry $providers, Nvl\Seo\Services\StructuredDataBuilder $builder): mixed
public function resolve(Illuminate\Database\Eloquent\Model $resource, array $context, ?array $persisted): array
@param (array<array-key, mixed> | null) $persisted
@param StructuredContext $context
resolved @param array{resourceType: string, resourceId: string, profileId: string, locale: string, scope: string, canonicalUrl: (string | null), title: (string | null), description: (string | null), imageUrl: (string | null), siteName: string, siteUrl: string} $context
@return list<array<string, mixed>>
```

## Nvl\Seo\Support\SeoAuthorizationContext

```text
final readonly class Nvl\Seo\Support\SeoAuthorizationContext
public function __construct(Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, Nvl\Seo\Enums\SeoAbility $ability, ?Nvl\Seo\Models\SeoProfile $profile=null, ?Illuminate\Database\Eloquent\Model $owner=null, ?string $ownerAlias=null, ?Illuminate\Database\Eloquent\Model $targetOwner=null, ?string $targetOwnerAlias=null, ?string $scope=null, ?string $redirectSource=null, ?string $redirectLocale=null): mixed
public readonly Nvl\Seo\Enums\SeoAbility $ability
public readonly Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor
public readonly ?Illuminate\Database\Eloquent\Model $owner
public readonly ?string $ownerAlias
public readonly ?Nvl\Seo\Models\SeoProfile $profile
public readonly ?string $redirectLocale
public readonly ?string $redirectSource
public readonly ?string $scope
public readonly ?Illuminate\Database\Eloquent\Model $targetOwner
public readonly ?string $targetOwnerAlias
```

## Nvl\Seo\Support\SeoImageContext

```text
final readonly class Nvl\Seo\Support\SeoImageContext
public function __construct(Nvl\Seo\Models\SeoProfile $profile, Nvl\Seo\Models\SeoProfileTranslation $translation, string $locale, ?string $url, ?string $reference, ?string $alt): mixed
public readonly ?string $alt
public readonly string $locale
public readonly Nvl\Seo\Models\SeoProfile $profile
public readonly ?string $reference
public readonly Nvl\Seo\Models\SeoProfileTranslation $translation
public readonly ?string $url
```

## Nvl\Seo\Support\SeoPath

```text
final class Nvl\Seo\Support\SeoPath
public static function hash(string $scope, string $locale, ?string $path): ?string
public static function normalize(?string $path): ?string
```

## Nvl\Seo\Traits\HasSeo

```text
trait Nvl\Seo\Traits\HasSeo
@mixin Model
resolved @mixin Illuminate\Database\Eloquent\Model
```

## Nvl\Seo\ValueObjects\SeoImage

```text
final readonly class Nvl\Seo\ValueObjects\SeoImage
public function __construct(string $url, ?string $alt=null, ?int $width=null, ?int $height=null, ?string $mimeType=null): mixed
public readonly ?string $alt
public readonly ?int $height
public readonly ?string $mimeType
public readonly string $url
public readonly ?int $width
```

## Nvl\Seo\ValueObjects\SitemapEntry

```text
final readonly class Nvl\Seo\ValueObjects\SitemapEntry
public function __construct(string $url, ?DateTimeInterface $lastModified=null, ?Nvl\Seo\Enums\SitemapChangeFrequency $changeFrequency=null, ?string $priority=null, array $alternates=[]): mixed
@param array<string, string> $alternates
public readonly array $alternates
@param array<string, string> $alternates
public readonly ?Nvl\Seo\Enums\SitemapChangeFrequency $changeFrequency
public readonly ?DateTimeInterface $lastModified
public readonly ?string $priority
public readonly string $url
```

## Nvl\Seo\ValueObjects\StructuredDataNode

```text
final readonly class Nvl\Seo\ValueObjects\StructuredDataNode
public function __construct(string $type, ?string $id=null, array $properties=[]): mixed
@param array<string, mixed> $properties
public static function make(string|Nvl\Seo\Enums\StructuredDataType $type, array $properties=[], ?string $id=null): self
@param array<string, mixed> $properties
public function toJsonLd(): array
@return array<string, mixed>
public readonly ?string $id
public readonly array $properties
@param array<string, mixed> $properties
public readonly string $type
```
