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

    /**
     * Create the stable mutex row boundary for redirect graph mutations.
     */
    public function up(): void
    {
        Schema::connection(PackageStorage::connection('seo'))->create(SeoTables::get(SeoTables::RedirectLocks), function (Blueprint $table): void {
            $table->string('name', 32)->primary();
        });
    }

    /**
     * Drop the redirect graph mutex table.
     */
    public function down(): void
    {
        Schema::connection(PackageStorage::connection('seo'))->dropIfExists(SeoTables::get(SeoTables::RedirectLocks));
    }
};
