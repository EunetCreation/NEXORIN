<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_relationships')) {
            Schema::create('company_relationships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();
                $table->string('tier', 30)->default('standard')->index();
                $table->string('qualification_state', 30)->default('not_assessed')->index();
                $table->string('source_type', 40)->nullable();
                $table->string('source_reference', 255)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_onboarding_requirements')) {
            Schema::create('company_onboarding_requirements', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('label', 150);
                $table->text('description')->nullable();
                $table->string('definition_version', 30)->default('1.0');
                $table->string('evaluation_strategy', 80);
                $table->boolean('required_by_default')->default(true)->index();
                $table->json('applicable_company_types')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            // These are product-policy definitions, not sample company data.
            // Optional evidence classes remain inactive in the progress denominator
            // until an organization configures them as required for a company type.
            DB::table('company_onboarding_requirements')->insert([
                [
                    'code' => 'company_identity',
                    'label' => 'Company Identity',
                    'description' => 'Core legal/display identity and company code are present.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'company_identity',
                    'required_by_default' => true,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'legal_registration',
                    'label' => 'Legal Registration',
                    'description' => 'A registration identifier or registration credential is recorded.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'legal_registration',
                    'required_by_default' => true,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'primary_contact',
                    'label' => 'Primary Contact',
                    'description' => 'A primary company contact is recorded.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'primary_contact',
                    'required_by_default' => true,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'service_region',
                    'label' => 'Service Region',
                    'description' => 'At least one operating/service region is recorded.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'service_region',
                    'required_by_default' => true,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 40,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'company_review',
                    'label' => 'Company Review',
                    'description' => 'The company profile has a recorded governance review timestamp.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'company_review',
                    'required_by_default' => true,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 50,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'required_certifications',
                    'label' => 'Required Certifications',
                    'description' => 'Certification evidence. Applicability must be configured by company type before becoming required.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'certifications',
                    'required_by_default' => false,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 60,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'insurance_evidence',
                    'label' => 'Insurance',
                    'description' => 'Insurance evidence. Applicability must be configured by company type before becoming required.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'insurance',
                    'required_by_default' => false,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 70,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'financial_documentation',
                    'label' => 'Financial Documentation',
                    'description' => 'Financial evidence. Applicability must be configured before becoming required.',
                    'definition_version' => '1.0',
                    'evaluation_strategy' => 'financial_documentation',
                    'required_by_default' => false,
                    'applicable_company_types' => null,
                    'is_active' => true,
                    'sort_order' => 80,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (! Schema::hasTable('company_directory_saved_views')) {
            Schema::create('company_directory_saved_views', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->json('filters');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'name']);
                $table->index(['user_id', 'is_default']);
            });
        }

        // Directory filters are server-side and expected to operate over large registries.
        // These additive indexes reuse Page 005 truth rather than copying searchable fields.
        if (Schema::hasTable('company_profiles')) {
            Schema::table('company_profiles', function (Blueprint $table): void {
                $table->index(['company_type', 'company_id'], 'company_profiles_type_company_idx');
            });
        }
        if (Schema::hasTable('company_regions')) {
            Schema::table('company_regions', function (Blueprint $table): void {
                $table->index(['region_name', 'company_id'], 'company_regions_region_company_idx');
            });
        }
        if (Schema::hasTable('company_contacts')) {
            Schema::table('company_contacts', function (Blueprint $table): void {
                $table->index(['company_id', 'is_primary'], 'company_contacts_primary_idx');
            });
        }
        if (Schema::hasTable('company_credentials')) {
            Schema::table('company_credentials', function (Blueprint $table): void {
                $table->index(['company_id', 'kind', 'status'], 'company_credentials_directory_idx');
                $table->index(['expires_at', 'company_id'], 'company_credentials_expiry_idx');
            });
        }
        if (Schema::hasTable('company_documents')) {
            Schema::table('company_documents', function (Blueprint $table): void {
                $table->index(['company_id', 'status'], 'company_documents_directory_idx');
                $table->index(['expires_at', 'company_id'], 'company_documents_expiry_idx');
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasTable('company_documents')) {
            Schema::table('company_documents', fn (Blueprint $table) => $table->dropIndex('company_documents_expiry_idx'));
            Schema::table('company_documents', fn (Blueprint $table) => $table->dropIndex('company_documents_directory_idx'));
        }
        if (Schema::hasTable('company_credentials')) {
            Schema::table('company_credentials', fn (Blueprint $table) => $table->dropIndex('company_credentials_expiry_idx'));
            Schema::table('company_credentials', fn (Blueprint $table) => $table->dropIndex('company_credentials_directory_idx'));
        }
        if (Schema::hasTable('company_contacts')) {
            Schema::table('company_contacts', fn (Blueprint $table) => $table->dropIndex('company_contacts_primary_idx'));
        }
        if (Schema::hasTable('company_regions')) {
            Schema::table('company_regions', fn (Blueprint $table) => $table->dropIndex('company_regions_region_company_idx'));
        }
        if (Schema::hasTable('company_profiles')) {
            Schema::table('company_profiles', fn (Blueprint $table) => $table->dropIndex('company_profiles_type_company_idx'));
        }

        Schema::dropIfExists('company_directory_saved_views');
        Schema::dropIfExists('company_onboarding_requirements');
        Schema::dropIfExists('company_relationships');
    }
};
