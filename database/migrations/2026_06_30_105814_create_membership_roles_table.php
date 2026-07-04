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
        Schema::create('membership_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_membership_id')
                ->constrained('company_memberships')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['company_membership_id', 'role_id'],
                'membership_roles_membership_role_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_roles');
    }
};