<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_page_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('media_type')->default('none'); // none, image, video
            $table->string('media_path')->nullable();
            $table->string('facebook_post_id')->nullable();
            $table->string('status')->default('pending'); // pending, published, failed
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
