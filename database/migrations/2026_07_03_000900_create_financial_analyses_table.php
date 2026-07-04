<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('technical_calculation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_of_material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('analysis_number', 50)->nullable()->unique();
            $table->string('status', 40)->default('draft');
            $table->string('currency', 10)->default('IDR');
            $table->decimal('electricity_tariff_idr_per_kwh', 16, 2)->default(0);
            $table->decimal('export_credit_percent', 8, 2)->default(0);
            $table->decimal('self_consumption_percent', 8, 2)->default(0);
            $table->decimal('tariff_escalation_percent', 8, 2)->default(0);
            $table->decimal('annual_om_percent', 8, 2)->default(0);
            $table->decimal('discount_rate_percent', 8, 2)->default(0);
            $table->decimal('energy_degradation_percent', 8, 2)->default(0);
            $table->unsignedTinyInteger('project_lifetime_years')->default(25);
            $table->unsignedTinyInteger('replacement_year')->default(0);
            $table->decimal('replacement_cost_idr', 18, 2)->default(0);
            $table->decimal('project_capex_idr', 18, 2)->default(0);
            $table->decimal('annual_energy_kwh', 16, 2)->default(0);
            $table->decimal('first_year_saving_idr', 18, 2)->default(0);
            $table->decimal('annual_om_cost_idr', 18, 2)->default(0);
            $table->decimal('simple_payback_years', 8, 2)->nullable();
            $table->decimal('lifetime_roi_percent', 10, 2)->default(0);
            $table->decimal('npv_idr', 18, 2)->default(0);
            $table->decimal('irr_percent', 10, 2)->nullable();
            $table->decimal('lcoe_idr_per_kwh', 16, 2)->nullable();
            $table->json('summary')->nullable();
            $table->json('cashflows')->nullable();
            $table->json('warnings')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_analyses');
    }
};
