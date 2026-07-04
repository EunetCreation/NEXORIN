<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('technical_calculations', function (Blueprint $table): void {
            $table->json('engine_results')->nullable()->after('results');
            $table->json('override_summary')->nullable()->after('engine_results');
        });

        Schema::create('technical_calculation_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technical_calculation_id')->constrained()->cascadeOnDelete();
            $table->string('field', 80);
            $table->string('label', 120);
            $table->string('unit', 40)->nullable();
            $table->string('value_type', 30)->default('numeric');
            $table->text('engine_value')->nullable();
            $table->text('previous_value')->nullable();
            $table->text('override_value')->nullable();
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->text('reset_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reset_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reset_at')->nullable();
            $table->timestamps();

            $table->index(['technical_calculation_id', 'field', 'is_active'], 'tc_override_calc_field_active_idx');
            $table->index(['company_id', 'project_id'], 'tc_override_company_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_calculation_overrides');

        Schema::table('technical_calculations', function (Blueprint $table): void {
            $table->dropColumn(['engine_results', 'override_summary']);
        });
    }
};
