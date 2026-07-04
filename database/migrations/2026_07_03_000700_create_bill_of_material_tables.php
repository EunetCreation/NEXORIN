<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bill_of_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('technical_calculation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bom_number', 50)->nullable()->unique();
            $table->string('status', 40)->default('draft');
            $table->json('summary')->nullable();
            $table->json('warnings')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bill_of_material_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('item_code', 80)->nullable();
            $table->string('item_name', 160);
            $table->text('description')->nullable();
            $table->json('specification')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('unit_cost_idr', 16, 2)->default(0);
            $table->decimal('unit_price_idr', 16, 2)->default(0);
            $table->decimal('line_cost_idr', 16, 2)->default(0);
            $table->decimal('line_price_idr', 16, 2)->default(0);
            $table->string('source_key', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['bill_of_material_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_material_items');
        Schema::dropIfExists('bill_of_materials');
    }
};
