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
        Schema::connection($this->getConnection())->table(SeoTables::get(SeoTables::I18n), static function (Blueprint $table): void {
            $table->string('robots')->nullable();
            $table->boolean('prepend_site_title')->nullable();
            $table->text('keywords')->nullable();
            $table->string('author')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table(SeoTables::get(SeoTables::I18n), static function (Blueprint $table): void {
            $table->dropColumn(['robots', 'prepend_site_title', 'keywords', 'author']);
        });
    }
};
