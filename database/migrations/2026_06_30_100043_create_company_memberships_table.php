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
        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status', 20)
                ->default('active')
                ->index();

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'user_id'],
                'company_memberships_company_user_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
    }
};