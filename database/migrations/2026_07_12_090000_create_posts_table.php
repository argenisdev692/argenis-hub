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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('post_title');
            $table->string('post_title_slug')->unique();
            $table->longText('post_content');
            $table->string('post_excerpt', 500)->nullable();
            $table->string('post_cover_image', 2048)->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords')->nullable();

            $table->foreignId('category_id')->nullable()
                ->constrained('blog_categories')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->onUpdate('cascade')->onDelete('set null');

            $table->string('post_status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // AI generation metadata — populated when the post was created/edited
            // via the AI assist panel (Post module: GeneratePostContentHandler).
            $table->boolean('is_ai_generated')->default(false);
            $table->string('ai_provider', 20)->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('eeat_score')->nullable();
            $table->unsignedTinyInteger('human_writing_index')->nullable();
            $table->unsignedTinyInteger('ai_detection_risk')->nullable();
            $table->json('ai_scores')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['post_status', 'created_at']);
            $table->index(['category_id', 'created_at']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
