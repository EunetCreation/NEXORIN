<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(
                ['project_id', 'changed_at'],
                'project_status_history_project_time_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_status_histories');
    }
};
