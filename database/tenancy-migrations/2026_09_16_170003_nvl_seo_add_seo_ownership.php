<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Support\Config\PackageStorage;
use Nvl\Support\Schema\SchemaConstraints;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('seo');
    }

    /** Expand SEO storage with resumable ownership and tenant-local natural keys. */
    public function up(): void
    {
        $schema = Schema::connection(PackageStorage::connection('seo'));
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::Profiles), function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->dropUnique('seo_profiles_scope_owner_unique');
            $table->dropIndex('seo_profiles_sitemap_scan_index');
            $table->unique(['tenant_id', 'id'], 'seo_profiles_tenant_id_unique');
            $table->unique(['tenant_id', 'scope', 'seoable_type', 'seoable_id'], 'seo_profiles_tenant_scope_owner_unique');
            $table->index(['tenant_id', 'scope', 'status', 'is_indexable', 'sitemap_included', 'id'], 'seo_profiles_tenant_sitemap_scan_idx');
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::I18n), function (Blueprint $table) use ($schema): void {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->dropUnique('seo_profiles_i18n_route_unique');
            SchemaConstraints::drop($schema, $table, 'foreign', ['seo_profile_id']);
            $table->unique(['tenant_id', 'scope', 'locale', 'path_hash'], 'seo_i18n_tenant_route_unique');
            $table->index(['tenant_id', 'locale', 'seo_profile_id'], 'seo_i18n_tenant_profile_idx');
            $table->foreign(['tenant_id', 'seo_profile_id'], 'seo_i18n_tenant_profile_foreign')
                ->references(['tenant_id', 'id'])->on(SeoTables::get(SeoTables::Profiles))->cascadeOnDelete();
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::Redirects), function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->dropUnique('seo_redirects_source_hash_unique');
            $table->unique(['tenant_id', 'source_hash'], 'seo_redirects_tenant_source_hash_unique');
            $table->index(['tenant_id', 'scope', 'locale', 'is_active', 'expires_at'], 'seo_redirects_tenant_lookup_idx');
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::RedirectLocks), function (Blueprint $table) use ($schema): void {
            SchemaConstraints::drop($schema, $table, 'primary');
            $table->uuid('tenant_id')->nullable()->first();
            $table->unique(['tenant_id', 'name'], 'seo_redirect_locks_tenant_name_unique');
        });
    }

    /** Restore the legacy global SEO identities. */
    public function down(): void
    {
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::RedirectLocks), function (Blueprint $table): void {
            $table->dropUnique('seo_redirect_locks_tenant_name_unique');
            $table->dropColumn('tenant_id');
            $table->primary('name');
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::Redirects), function (Blueprint $table): void {
            $table->dropIndex('seo_redirects_tenant_lookup_idx');
            $table->dropUnique('seo_redirects_tenant_source_hash_unique');
            $table->unique('source_hash', 'seo_redirects_source_hash_unique');
            $table->dropColumn('tenant_id');
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::I18n), function (Blueprint $table): void {
            $table->dropForeign('seo_i18n_tenant_profile_foreign');
            $table->dropIndex('seo_i18n_tenant_profile_idx');
            $table->dropUnique('seo_i18n_tenant_route_unique');
            $table->dropColumn('tenant_id');
            $table->unique(['scope', 'locale', 'path_hash'], 'seo_profiles_i18n_route_unique');
            $table->foreign('seo_profile_id')->references('id')->on(SeoTables::get(SeoTables::Profiles))->cascadeOnDelete();
        });
        Schema::connection(PackageStorage::connection('seo'))->table(SeoTables::get(SeoTables::Profiles), function (Blueprint $table): void {
            $table->dropIndex('seo_profiles_tenant_sitemap_scan_idx');
            $table->dropUnique('seo_profiles_tenant_scope_owner_unique');
            $table->dropUnique('seo_profiles_tenant_id_unique');
            $table->dropColumn('tenant_id');
            $table->unique(['scope', 'seoable_type', 'seoable_id'], 'seo_profiles_scope_owner_unique');
            $table->index(['scope', 'status', 'is_indexable', 'sitemap_included', 'id'], 'seo_profiles_sitemap_scan_index');
        });
    }
};
