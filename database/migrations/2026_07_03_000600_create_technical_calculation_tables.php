<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('technical_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('system_type', 30)->nullable();
            $table->decimal('energy_offset_target_percent', 5, 2)->default(80);
            $table->decimal('performance_ratio', 5, 4)->default(0.78);
            $table->decimal('module_power_wp', 10, 2)->default(550);
            $table->decimal('module_area_m2', 8, 3)->default(2.58);
            $table->decimal('module_voc_v', 8, 3)->default(49.90);
            $table->decimal('module_vmp_v', 8, 3)->default(41.80);
            $table->decimal('module_isc_a', 8, 3)->default(13.90);
            $table->decimal('module_imp_a', 8, 3)->default(13.16);
            $table->decimal('module_voc_temp_coeff_percent_c', 8, 4)->default(-0.25);
            $table->decimal('module_vmp_temp_coeff_percent_c', 8, 4)->default(-0.29);
            $table->decimal('minimum_site_temperature_c', 6, 2)->default(20);
            $table->decimal('maximum_cell_temperature_c', 6, 2)->default(70);
            $table->decimal('inverter_ac_power_kw', 10, 3)->default(5);
            $table->decimal('inverter_max_dc_voltage_v', 10, 2)->default(600);
            $table->decimal('inverter_mppt_min_voltage_v', 10, 2)->default(120);
            $table->decimal('inverter_mppt_max_voltage_v', 10, 2)->default(550);
            $table->decimal('inverter_max_input_current_a', 10, 2)->default(32);
            $table->unsignedSmallInteger('inverter_mppt_count')->default(2);
            $table->decimal('desired_dc_ac_ratio', 6, 3)->default(1.15);
            $table->decimal('battery_backup_hours', 8, 2)->default(0);
            $table->decimal('battery_dod_percent', 5, 2)->default(90);
            $table->decimal('battery_efficiency_percent', 5, 2)->default(95);
            $table->decimal('inverter_efficiency_percent', 5, 2)->default(96);
            $table->decimal('dc_cable_length_m', 10, 2)->default(20);
            $table->decimal('dc_cable_area_mm2', 8, 2)->default(6);
            $table->decimal('ac_cable_length_m', 10, 2)->default(15);
            $table->decimal('ac_cable_area_mm2', 8, 2)->default(6);
            $table->string('cable_material', 20)->default('copper');
            $table->decimal('maximum_voltage_drop_percent', 5, 2)->default(2);
            $table->string('result_status', 40)->default('preliminary');
            $table->json('results')->nullable();
            $table->json('warnings')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->text('engineering_notes')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('technical_calculation_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('technical_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_calculation_histories');
        Schema::dropIfExists('technical_calculations');
    }
};
