<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the Page 005 company extension tables.
     *
     * This migration is intentionally recovery-safe because Page 005 went
     * through several additive patch revisions. A developer database may
     * already contain one or more of these tables even when this exact
     * migration is not present in the migrations table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('company_profiles')) {
            Schema::create('company_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();
                $table->string('profile_image_path', 500)->nullable();
                $table->string('legal_name', 190)->nullable();
                $table->string('company_type', 100)->nullable();
                $table->unsignedSmallInteger('year_established')->nullable();
                $table->string('website', 255)->nullable();
                $table->string('hq_country', 100)->nullable();
                $table->string('hq_city', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('data_confidence', 20)->default('medium');
                $table->json('categories_served')->nullable();
                $table->json('focus_markets')->nullable();
                $table->unsignedInteger('team_size')->nullable();
                $table->string('parent_company_name', 190)->nullable();
                $table->string('parent_company_country', 100)->nullable();
                $table->decimal('annual_revenue_usd', 15, 2)->nullable();
                $table->decimal('ebitda_margin', 7, 2)->nullable();
                $table->decimal('current_ratio', 9, 2)->nullable();
                $table->decimal('total_assets_usd', 15, 2)->nullable();
                $table->decimal('net_profit_usd', 15, 2)->nullable();
                $table->decimal('debt_to_equity', 9, 2)->nullable();
                $table->unsignedTinyInteger('performance_score')->nullable();
                $table->timestamp('last_reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_contacts')) {
            Schema::create('company_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('full_name', 150);
                $table->string('job_title', 150)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_regions')) {
            Schema::create('company_regions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('country_name', 100);
                $table->string('region_name', 120)->nullable();
                $table->unsignedInteger('project_count')->default(0);
                $table->boolean('is_headquarters')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_documents')) {
            Schema::create('company_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('category', 40)->default('general')->index();
                $table->string('title', 190);
                $table->string('file_name', 190)->nullable();
                $table->unsignedInteger('file_size_kb')->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_credentials')) {
            Schema::create('company_credentials', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 20)->index();
                $table->string('title', 190);
                $table->string('reference_number', 120)->nullable();
                $table->string('issuing_authority', 190)->nullable();
                $table->string('policy_provider', 190)->nullable();
                $table->decimal('coverage_amount_usd', 15, 2)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_credentials');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('company_regions');
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('company_profiles');
    }
};
