<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Seo\Definitions\Tables\SeoTables;
use Nvl\Support\Config\PackageStorage;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('seo');
    }

    public function up(): void
    {
        foreach ([SeoTables::get(SeoTables::Profiles), SeoTables::get(SeoTables::I18n), SeoTables::get(SeoTables::Redirects), SeoTables::get(SeoTables::RedirectLocks)] as $table) {
            Schema::connection(PackageStorage::connection('seo'))->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable(false)->change());
        }
    }

    public function down(): void
    {
        foreach ([SeoTables::get(SeoTables::Profiles), SeoTables::get(SeoTables::I18n), SeoTables::get(SeoTables::Redirects), SeoTables::get(SeoTables::RedirectLocks)] as $table) {
            Schema::connection(PackageStorage::connection('seo'))->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable()->change());
        }
    }
};
