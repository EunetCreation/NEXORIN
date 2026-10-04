<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_solar_profiles', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('project_solar_profiles')
            ->whereNull('timezone')
            ->update(['timezone' => 'Asia/Jakarta']);

        Schema::table('project_solar_profiles', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable(false)->default('Asia/Jakarta')->change();
        });
    }
};
