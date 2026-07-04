<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('grid_connection_status', 40)->default('stable');
            $table->unsignedSmallInteger('outage_frequency_per_month')->default(0);
            $table->decimal('average_outage_duration_hours', 7, 2)->default(0);
            $table->decimal('backup_target_hours', 7, 2)->default(0);
            $table->decimal('target_self_sufficiency_percent', 6, 2)->default(30);
            $table->string('budget_flexibility', 30)->default('balanced');
            $table->string('export_policy', 30)->default('unknown');
            $table->string('primary_objective', 40)->default('balanced');
            $table->text('notes')->nullable();

            $table->string('engine_recommended_system', 30)->nullable();
            $table->string('recommended_system', 30)->nullable();
            $table->decimal('on_grid_score', 7, 2)->nullable();
            $table->decimal('hybrid_score', 7, 2)->nullable();
            $table->decimal('decisiveness_index', 7, 2)->nullable();
            $table->string('result_status', 30)->default('incomplete');
            $table->string('engine_version', 30)->default('1.0.0');

            $table->json('hard_constraints')->nullable();
            $table->json('warnings')->nullable();
            $table->json('reasons')->nullable();
            $table->json('source_snapshot')->nullable();

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->string('override_system', 30)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'result_status']);
            $table->index(['company_id', 'recommended_system']);
        });

        Schema::create('system_recommendation_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('alternative', 30);
            $table->string('criterion_code', 60);
            $table->string('criterion_name', 120);
            $table->decimal('weight', 6, 4);
            $table->decimal('rating', 7, 2);
            $table->decimal('weighted_score', 8, 4);
            $table->text('rationale')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['system_recommendation_id', 'alternative', 'criterion_code'],
                'recommendation_alt_criterion_unique'
            );
            $table->index(['project_id', 'alternative']);
        });

        Schema::create('system_recommendation_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_system', 30)->nullable();
            $table->string('new_system', 30)->nullable();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('summary')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['project_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_recommendation_histories');
        Schema::dropIfExists('system_recommendation_scores');
        Schema::dropIfExists('system_recommendations');
    }
};
