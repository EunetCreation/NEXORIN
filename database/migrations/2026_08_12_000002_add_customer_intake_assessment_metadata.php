<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_profiles', 'parent_company_assessment')) {
                $table->string('parent_company_assessment', 24)
                    ->default('not_assessed')
                    ->after('operating_countries');
            }

            if (! Schema::hasColumn('customer_profiles', 'opportunity_assessment')) {
                $table->string('opportunity_assessment', 24)
                    ->default('not_assessed')
                    ->after('ownership_pct');
            }

            if (! Schema::hasColumn('customer_profiles', 'review_acknowledged_at')) {
                $table->timestamp('review_acknowledged_at')
                    ->nullable()
                    ->after('key_considerations');
            }

            if (! Schema::hasColumn('customer_profiles', 'review_acknowledged_by')) {
                $table->foreignId('review_acknowledged_by')
                    ->nullable()
                    ->after('review_acknowledged_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            return;
        }

        Schema::table('customer_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_profiles', 'review_acknowledged_by')) {
                $table->dropConstrainedForeignId('review_acknowledged_by');
            }

            $columns = collect([
                'parent_company_assessment',
                'opportunity_assessment',
                'review_acknowledged_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('customer_profiles', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
