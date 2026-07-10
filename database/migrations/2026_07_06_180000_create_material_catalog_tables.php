<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 80);
            $table->string('name', 180);
            $table->string('brand', 120)->nullable();
            $table->string('model', 140)->nullable();
            $table->string('material_type', 80)->nullable();
            $table->string('cost_group', 80)->nullable();
            $table->string('uom', 30)->default('pcs');
            $table->json('system_types')->nullable();
            $table->json('project_scales')->nullable();
            $table->json('suitable_roof_types')->nullable();
            $table->decimal('nominal_power_wp', 12, 2)->nullable();
            $table->decimal('nominal_capacity_kwh', 12, 3)->nullable();
            $table->decimal('rated_power_kw', 12, 3)->nullable();
            $table->decimal('voltage_v', 12, 2)->nullable();
            $table->decimal('current_a', 12, 2)->nullable();
            $table->string('electrical_phase', 40)->nullable();
            $table->string('ip_rating', 40)->nullable();
            $table->unsignedSmallInteger('warranty_years')->nullable();
            $table->string('default_scope_mode', 50)->default('catalog_candidate');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->text('technical_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['material_category_id', 'is_active']);
            $table->index(['sku', 'brand', 'model']);
        });

        Schema::create('material_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('spec_key', 100);
            $table->string('spec_label', 140);
            $table->string('spec_value', 220)->nullable();
            $table->string('spec_unit', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->timestamps();

            $table->unique(['material_id', 'spec_key']);
            $table->index(['material_id', 'sort_order']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_code', 80);
            $table->string('name', 180);
            $table->string('contact_person', 120)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('website', 180)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->string('payment_terms', 180)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('price_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->string('currency', 10)->default('IDR');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('pricing_basis', 80)->default('supplier_quote');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('price_book_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('selling_price', 16, 2)->nullable();
            $table->decimal('margin_percent', 8, 2)->nullable();
            $table->decimal('minimum_order_quantity', 12, 3)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->string('availability_status', 40)->default('available');
            $table->text('warranty_note')->nullable();
            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['price_book_id', 'material_id']);
            $table->index(['material_id', 'availability_status']);
            $table->index(['supplier_id', 'availability_status']);
        });

        Schema::create('material_datasheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('file_path', 255)->nullable();
            $table->string('external_url', 255)->nullable();
            $table->string('revision', 60)->nullable();
            $table->date('issued_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'is_active']);
        });

        Schema::create('material_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_type', 80);
            $table->string('title', 180);
            $table->string('certificate_number', 120)->nullable();
            $table->string('issuer', 160)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('external_url', 255)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'certificate_type']);
            $table->index(['expires_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_certificates');
        Schema::dropIfExists('material_datasheets');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('material_specs');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
    }
};
