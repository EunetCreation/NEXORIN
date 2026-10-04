<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete()->unique();
                $table->string('preferred_name', 150)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('tax_id', 120)->nullable();
                $table->string('registration_number', 120)->nullable();
                $table->string('currency_code', 3)->nullable();
                $table->string('timezone', 80)->nullable();
                $table->unsignedSmallInteger('year_established')->nullable();
                $table->unsignedInteger('employee_count')->nullable();
                $table->decimal('annual_revenue_amount', 18, 2)->nullable();
                $table->string('revenue_currency_code', 3)->nullable();
                $table->text('description')->nullable();
                $table->string('relationship_stage', 30)->default('lead')->index();
                $table->string('primary_industry', 120)->nullable()->index();
                $table->string('sub_industry', 120)->nullable();
                $table->string('business_model', 120)->nullable();
                $table->json('industry_tags')->nullable();
                $table->string('hq_country', 100)->nullable();
                $table->json('operating_countries')->nullable();
                $table->foreignId('parent_customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('external_parent_name', 190)->nullable();
                $table->string('parent_company_country', 100)->nullable();
                $table->decimal('ownership_pct', 5, 2)->nullable();
                $table->foreignId('account_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sales_team', 120)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('business_unit', 120)->nullable();
                $table->string('preferred_contract_type', 100)->nullable();
                $table->string('engagement_model', 100)->nullable();
                $table->string('payment_terms', 100)->nullable();
                $table->string('contract_duration', 100)->nullable();
                $table->text('key_considerations')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'relationship_stage'], 'customer_profiles_company_stage_idx');
                $table->index(['company_id', 'account_owner_user_id'], 'customer_profiles_company_owner_idx');
                $table->index(['company_id', 'parent_customer_id'], 'customer_profiles_company_parent_idx');
            });
        }

        if (! Schema::hasTable('customer_contacts')) {
            Schema::create('customer_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('full_name', 150);
                $table->string('job_title', 150)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'customer_id', 'is_primary'], 'customer_contacts_scope_primary_idx');
            });
        }

        if (! Schema::hasTable('customer_opportunities')) {
            Schema::create('customer_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('name', 190);
                $table->string('opportunity_type', 80)->nullable();
                $table->decimal('estimated_value', 18, 2)->nullable();
                $table->string('currency_code', 3)->nullable();
                $table->unsignedTinyInteger('probability_pct')->nullable();
                $table->date('expected_close_date')->nullable();
                $table->string('status', 30)->default('open')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'status', 'expected_close_date'], 'customer_opportunities_scope_status_idx');
                $table->index(['customer_id', 'status'], 'customer_opportunities_customer_status_idx');
            });
        }

        if (! Schema::hasTable('customer_documents')) {
            Schema::create('customer_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('category', 60)->index();
                $table->string('title', 190);
                $table->string('original_name', 255);
                $table->string('storage_disk', 40)->default('local');
                $table->string('storage_path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('sha256', 64)->nullable()->index();
                $table->string('status', 30)->default('recorded')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'customer_id', 'category'], 'customer_documents_scope_category_idx');
            });
        }

        if (! Schema::hasTable('customer_create_drafts')) {
            Schema::create('customer_create_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->json('payload');
                $table->timestamp('saved_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'user_id'], 'customer_create_drafts_scope_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_create_drafts');
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_opportunities');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_profiles');
    }
};
