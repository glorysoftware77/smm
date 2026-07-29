<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('post_format')->default('standard')->after('media_type'); // standard, reel
            $table->string('title')->nullable()->after('message');
            $table->string('facebook_video_id')->nullable()->after('facebook_post_id');
            $table->json('insights')->nullable()->after('error_message');
            $table->timestamp('insights_fetched_at')->nullable()->after('insights');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'post_format',
                'title',
                'facebook_video_id',
                'insights',
                'insights_fetched_at',
            ]);
        });
    }
};
