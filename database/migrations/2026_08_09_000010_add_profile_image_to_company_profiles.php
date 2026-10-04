<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_profiles') && ! Schema::hasColumn('company_profiles', 'profile_image_path')) {
            Schema::table('company_profiles', function (Blueprint $table): void {
                $table->string('profile_image_path', 500)->nullable()->after('company_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_profiles') && Schema::hasColumn('company_profiles', 'profile_image_path')) {
            Schema::table('company_profiles', function (Blueprint $table): void {
                $table->dropColumn('profile_image_path');
            });
        }
    }
};
