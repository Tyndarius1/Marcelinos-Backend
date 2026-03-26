<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('embed_src');
            $table->unsignedSmallInteger('iframe_width')->default(500);
            $table->unsignedSmallInteger('iframe_height')->default(645);
            $table->text('meta_description');
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->text('excerpt');
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
