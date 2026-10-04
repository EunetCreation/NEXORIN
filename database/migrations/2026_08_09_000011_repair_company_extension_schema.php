<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile Page 005 tables that may already exist from an earlier patch
     * but do not yet contain the complete current schema.
     *
     * This migration is additive only: it never drops, renames, or rewrites
     * existing columns/data. Required fields are added nullable when an older
     * table may already contain rows, while normal application validation keeps
     * new records well-formed.
     */
    public function up(): void
    {
        $this->repairCompanyProfiles();
        $this->repairCompanyContacts();
        $this->repairCompanyRegions();
        $this->repairCompanyDocuments();
        $this->repairCompanyCredentials();
        $this->repairCompanyEditDrafts();
        $this->repairCompanyFinancialSnapshots();
    }

    public function down(): void
    {
        // Intentionally no destructive rollback. This is a recovery/reconciliation
        // migration for developer databases that may contain pre-existing data.
    }

    private function repairCompanyProfiles(): void
    {
        if (! Schema::hasTable('company_profiles')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_profiles'));

        Schema::table('company_profiles', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['profile_image_path'])) $table->string('profile_image_path', 500)->nullable();
            if (! isset($columns['legal_name'])) $table->string('legal_name', 190)->nullable();
            if (! isset($columns['company_type'])) $table->string('company_type', 100)->nullable();
            if (! isset($columns['year_established'])) $table->unsignedSmallInteger('year_established')->nullable();
            if (! isset($columns['website'])) $table->string('website', 255)->nullable();
            if (! isset($columns['hq_country'])) $table->string('hq_country', 100)->nullable();
            if (! isset($columns['hq_city'])) $table->string('hq_city', 120)->nullable();
            if (! isset($columns['description'])) $table->text('description')->nullable();
            if (! isset($columns['data_confidence'])) $table->string('data_confidence', 20)->default('medium');
            if (! isset($columns['categories_served'])) $table->json('categories_served')->nullable();
            if (! isset($columns['focus_markets'])) $table->json('focus_markets')->nullable();
            if (! isset($columns['team_size'])) $table->unsignedInteger('team_size')->nullable();
            if (! isset($columns['parent_company_name'])) $table->string('parent_company_name', 190)->nullable();
            if (! isset($columns['parent_company_country'])) $table->string('parent_company_country', 100)->nullable();
            if (! isset($columns['annual_revenue_usd'])) $table->decimal('annual_revenue_usd', 15, 2)->nullable();
            if (! isset($columns['ebitda_margin'])) $table->decimal('ebitda_margin', 7, 2)->nullable();
            if (! isset($columns['current_ratio'])) $table->decimal('current_ratio', 9, 2)->nullable();
            if (! isset($columns['total_assets_usd'])) $table->decimal('total_assets_usd', 15, 2)->nullable();
            if (! isset($columns['net_profit_usd'])) $table->decimal('net_profit_usd', 15, 2)->nullable();
            if (! isset($columns['debt_to_equity'])) $table->decimal('debt_to_equity', 9, 2)->nullable();
            if (! isset($columns['performance_score'])) $table->unsignedTinyInteger('performance_score')->nullable();
            if (! isset($columns['last_reviewed_at'])) $table->timestamp('last_reviewed_at')->nullable();
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyContacts(): void
    {
        if (! Schema::hasTable('company_contacts')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_contacts'));

        Schema::table('company_contacts', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['full_name'])) $table->string('full_name', 150)->nullable();
            if (! isset($columns['job_title'])) $table->string('job_title', 150)->nullable();
            if (! isset($columns['email'])) $table->string('email', 190)->nullable();
            if (! isset($columns['phone'])) $table->string('phone', 40)->nullable();
            if (! isset($columns['is_primary'])) $table->boolean('is_primary')->default(false);
            if (! isset($columns['sort_order'])) $table->unsignedSmallInteger('sort_order')->default(0);
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyRegions(): void
    {
        if (! Schema::hasTable('company_regions')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_regions'));

        Schema::table('company_regions', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['country_name'])) $table->string('country_name', 100)->nullable();
            if (! isset($columns['country_code'])) $table->string('country_code', 3)->nullable();
            if (! isset($columns['region_name'])) $table->string('region_name', 120)->nullable();
            if (! isset($columns['latitude'])) $table->decimal('latitude', 10, 7)->nullable();
            if (! isset($columns['longitude'])) $table->decimal('longitude', 10, 7)->nullable();
            if (! isset($columns['project_count'])) $table->unsignedInteger('project_count')->default(0);
            if (! isset($columns['is_headquarters'])) $table->boolean('is_headquarters')->default(false);
            if (! isset($columns['sort_order'])) $table->unsignedSmallInteger('sort_order')->default(0);
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyDocuments(): void
    {
        if (! Schema::hasTable('company_documents')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_documents'));

        Schema::table('company_documents', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['category'])) $table->string('category', 40)->default('general');
            if (! isset($columns['title'])) $table->string('title', 190)->nullable();
            if (! isset($columns['file_name'])) $table->string('file_name', 190)->nullable();
            if (! isset($columns['file_size_kb'])) $table->unsignedInteger('file_size_kb')->nullable();
            if (! isset($columns['issued_at'])) $table->date('issued_at')->nullable();
            if (! isset($columns['expires_at'])) $table->date('expires_at')->nullable();
            if (! isset($columns['status'])) $table->string('status', 20)->default('draft');
            if (! isset($columns['sort_order'])) $table->unsignedSmallInteger('sort_order')->default(0);
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyCredentials(): void
    {
        if (! Schema::hasTable('company_credentials')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_credentials'));

        Schema::table('company_credentials', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['kind'])) $table->string('kind', 20)->nullable();
            if (! isset($columns['title'])) $table->string('title', 190)->nullable();
            if (! isset($columns['reference_number'])) $table->string('reference_number', 120)->nullable();
            if (! isset($columns['issuing_authority'])) $table->string('issuing_authority', 190)->nullable();
            if (! isset($columns['policy_provider'])) $table->string('policy_provider', 190)->nullable();
            if (! isset($columns['coverage_amount_usd'])) $table->decimal('coverage_amount_usd', 15, 2)->nullable();
            if (! isset($columns['issued_at'])) $table->date('issued_at')->nullable();
            if (! isset($columns['expires_at'])) $table->date('expires_at')->nullable();
            if (! isset($columns['status'])) $table->string('status', 20)->default('pending');
            if (! isset($columns['sort_order'])) $table->unsignedSmallInteger('sort_order')->default(0);
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyEditDrafts(): void
    {
        if (! Schema::hasTable('company_edit_drafts')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_edit_drafts'));

        Schema::table('company_edit_drafts', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['user_id'])) $table->unsignedBigInteger('user_id')->nullable();
            if (! isset($columns['payload'])) $table->json('payload')->nullable();
            if (! isset($columns['status'])) $table->string('status', 20)->default('draft');
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }

    private function repairCompanyFinancialSnapshots(): void
    {
        if (! Schema::hasTable('company_financial_snapshots')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('company_financial_snapshots'));

        Schema::table('company_financial_snapshots', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['company_id'])) $table->unsignedBigInteger('company_id')->nullable();
            if (! isset($columns['period_end'])) $table->date('period_end')->nullable();
            if (! isset($columns['currency'])) $table->string('currency', 3)->default('USD');
            if (! isset($columns['annual_revenue'])) $table->decimal('annual_revenue', 15, 2)->nullable();
            if (! isset($columns['ebitda_margin'])) $table->decimal('ebitda_margin', 7, 2)->nullable();
            if (! isset($columns['current_ratio'])) $table->decimal('current_ratio', 9, 2)->nullable();
            if (! isset($columns['total_assets'])) $table->decimal('total_assets', 15, 2)->nullable();
            if (! isset($columns['net_profit'])) $table->decimal('net_profit', 15, 2)->nullable();
            if (! isset($columns['debt_to_equity'])) $table->decimal('debt_to_equity', 9, 2)->nullable();
            if (! isset($columns['source_document_id'])) $table->unsignedBigInteger('source_document_id')->nullable();
            if (! isset($columns['recorded_by'])) $table->unsignedBigInteger('recorded_by')->nullable();
            if (! isset($columns['created_at'])) $table->timestamp('created_at')->nullable();
            if (! isset($columns['updated_at'])) $table->timestamp('updated_at')->nullable();
        });
    }
};
