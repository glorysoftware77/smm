<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_pages', function (Blueprint $table) {
            $table->foreignId('linked_social_page_id')
                ->nullable()
                ->after('social_account_id')
                ->constrained('social_pages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_social_page_id');
        });
    }
};
