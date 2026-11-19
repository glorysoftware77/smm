<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // facebook
            $table->string('provider_user_id');
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'provider_user_id']);
        });

        Schema::create('social_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // facebook
            $table->string('page_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('picture_url')->nullable();
            $table->text('access_token');
            $table->boolean('is_connected')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_pages');
        Schema::dropIfExists('social_accounts');
    }
};
