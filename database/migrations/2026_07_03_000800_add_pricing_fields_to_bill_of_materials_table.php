<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table): void {
            $table->string('pricing_status', 40)->default('not_priced');
            $table->decimal('material_cost_subtotal_idr', 16, 2)->default(0);
            $table->decimal('material_price_subtotal_idr', 16, 2)->default(0);
            $table->decimal('labor_cost_idr', 16, 2)->default(0);
            $table->decimal('logistics_cost_idr', 16, 2)->default(0);
            $table->decimal('other_cost_idr', 16, 2)->default(0);
            $table->decimal('contingency_cost_idr', 16, 2)->default(0);
            $table->decimal('tax_idr', 16, 2)->default(0);
            $table->decimal('discount_idr', 16, 2)->default(0);
            $table->decimal('grand_cost_idr', 16, 2)->default(0);
            $table->decimal('grand_price_idr', 16, 2)->default(0);
            $table->decimal('gross_margin_idr', 16, 2)->default(0);
            $table->decimal('gross_margin_percent', 8, 2)->default(0);
            $table->json('pricing_parameters')->nullable();
            $table->foreignId('priced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('priced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bill_of_materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('priced_by');
            $table->dropColumn([
                'pricing_status',
                'material_cost_subtotal_idr',
                'material_price_subtotal_idr',
                'labor_cost_idr',
                'logistics_cost_idr',
                'other_cost_idr',
                'contingency_cost_idr',
                'tax_idr',
                'discount_idr',
                'grand_cost_idr',
                'grand_price_idr',
                'gross_margin_idr',
                'gross_margin_percent',
                'pricing_parameters',
                'priced_at',
            ]);
        });
    }
};
