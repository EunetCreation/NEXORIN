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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 100)->unique();
            $table->string('name', 150);

            $table->string('module', 50)->index();
            $table->string('action', 50);

            $table->text('description')->nullable();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->unique(
                ['module', 'action'],
                'permissions_module_action_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};