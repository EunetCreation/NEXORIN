<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_regions')) {
            return;
        }

        if (! Schema::hasColumn('company_regions', 'country_code')) {
            Schema::table('company_regions', function (Blueprint $table): void {
                $table->string('country_code', 3)->nullable()->after('country_name');
            });
        }

        if (! Schema::hasColumn('company_regions', 'latitude')) {
            Schema::table('company_regions', function (Blueprint $table): void {
                $table->decimal('latitude', 10, 7)->nullable()->after('region_name');
            });
        }

        if (! Schema::hasColumn('company_regions', 'longitude')) {
            Schema::table('company_regions', function (Blueprint $table): void {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }

        // Avoid a named-index collision on databases that already received an
        // earlier Page 005 patch. PostgreSQL can query country_code efficiently
        // after the migration; a dedicated index can be added later through a
        // separately named migration if profiling shows it is necessary.
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_regions')) {
            return;
        }

        $columns = collect(['country_code', 'latitude', 'longitude'])
            ->filter(fn (string $column) => Schema::hasColumn('company_regions', $column))
            ->values()
            ->all();

        if ($columns !== []) {
            Schema::table('company_regions', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
