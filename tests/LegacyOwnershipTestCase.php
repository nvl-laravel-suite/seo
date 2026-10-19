<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\ValueObjects\OwnershipMapping;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Tests\Fixtures\OwnershipCompany;
use Nvl\Seo\Tests\Fixtures\OwnershipContentOwner;

/** Seeds reviewed legacy rows, including a tenant-salted redirect and composite-only child FK. */
abstract class LegacyOwnershipTestCase extends OwnershipTestCase
{
    public const string SUBJECT = '10000000-0000-4000-8000-000000000001';

    public const string PROFILE = '10000000-0000-4000-8000-000000000002';

    public const string COPY = '10000000-0000-4000-8000-000000000003';

    public const string REDIRECT = '10000000-0000-4000-8000-000000000004';

    public const string TENANT = '10000000-0000-4000-8000-000000000007';

    protected function reviewedMappings(): array
    {
        $connection = (new SeoProfile)->getConnection();
        $schema = $connection->getSchemaBuilder();
        $profiles = SeoTables::get(SeoTables::Profiles);
        $copy = SeoTables::get(SeoTables::I18n);
        $redirects = SeoTables::get(SeoTables::Redirects);
        foreach ([$profiles, $copy, $redirects] as $table) {
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id'));
        }
        foreach ($schema->getForeignKeys($copy) as $foreign) {
            if ($foreign['columns'] === ['seo_profile_id']) {
                $schema->table($copy, static fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? ['seo_profile_id']));
            }
        }
        $schema->table($profiles, static fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        $schema->table($copy, static fn (Blueprint $table) => $table->foreign(['tenant_id', 'seo_profile_id'])->references(['tenant_id', 'id'])->on($profiles));
        $connection->table((new OwnershipContentOwner)->getTable())->insert(['id' => self::SUBJECT, 'company_id' => 7, 'name' => 'Legacy subject']);
        $connection->table($profiles)->insert(['id' => self::PROFILE, 'tenant_id' => self::TENANT, 'seoable_type' => (new OwnershipContentOwner)->getMorphClass(), 'seoable_id' => self::SUBJECT]);
        $connection->table($copy)->insert(['id' => self::COPY, 'tenant_id' => self::TENANT, 'seo_profile_id' => self::PROFILE, 'scope' => 'default', 'locale' => 'en', 'path' => '/legacy', 'path_hash' => SeoPath::hash('default', 'en', '/legacy'), 'title' => 'Legacy SEO']);
        $connection->table($redirects)->insert(['id' => self::REDIRECT, 'tenant_id' => self::TENANT, 'source_path' => '/old-legacy', 'source_hash' => SeoRedirect::sourceHashForTenant(self::TENANT, 'default', null, '/old-legacy'), 'target' => '/legacy']);
        $owner = app(OwnerIdentityResolver::class)->reference(OwnershipCompany::findOrFail(7));

        return [
            new OwnershipMapping('fixture.content-owners', self::SUBJECT, $owner),
            new OwnershipMapping('seo.profiles', self::PROFILE, $owner, legacyTenantId: self::TENANT),
            new OwnershipMapping('seo.translations', self::COPY, $owner, legacyTenantId: self::TENANT),
            new OwnershipMapping('seo.redirects', self::REDIRECT, $owner, legacyTenantId: self::TENANT),
        ];
    }
}
