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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('code', 30)->unique();

            $table->string('registration_number', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('status', 20)
                ->default('active')
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};