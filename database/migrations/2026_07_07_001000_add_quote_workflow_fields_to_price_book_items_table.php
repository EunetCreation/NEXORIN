<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('price_book_items', function (Blueprint $table): void {
            $table->string('quote_status', 30)->default('active')->index();
            $table->string('import_batch_code', 80)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('price_book_items', function (Blueprint $table): void {
            $table->dropColumn(['quote_status', 'import_batch_code']);
        });
    }
};
