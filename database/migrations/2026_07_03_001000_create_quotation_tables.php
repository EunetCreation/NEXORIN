<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('financial_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_of_material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quote_number', 70)->nullable()->unique();
            $table->unsignedSmallInteger('revision_number')->default(0);
            $table->string('status', 40)->default('draft');
            $table->string('currency', 10)->default('IDR');
            $table->string('title', 180)->nullable();
            $table->date('valid_until')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('project_snapshot')->nullable();
            $table->json('technical_summary')->nullable();
            $table->json('financial_summary')->nullable();
            $table->json('commercial_summary')->nullable();
            $table->json('terms')->nullable();
            $table->json('warnings')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'valid_until']);
        });

        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('category', 80)->nullable();
            $table->string('item_code', 80)->nullable();
            $table->string('item_name', 180);
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit', 30)->default('unit');
            $table->decimal('unit_price_idr', 18, 2)->default(0);
            $table->decimal('line_price_idr', 18, 2)->default(0);
            $table->string('source_key', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
