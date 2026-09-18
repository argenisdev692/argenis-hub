<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_cv_structures', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_id');
            $table->char('source_text_hash', 64)->nullable();
            $table->string('parser_version', 32)->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('profile_facts')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cv_id']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_cv_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('studio_cv_structures')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedInteger('ordinal')->default(0);
            $table->string('organization')->nullable();
            $table->string('role_title')->nullable();
            $table->string('location')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['structure_id', 'ordinal']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_cv_bullets', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('studio_cv_structures')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('studio_cv_entries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('ordinal')->default(0);
            $table->text('text');
            $table->boolean('has_metric')->default(false);
            $table->boolean('xyz_complete')->default(false);
            $table->unsignedInteger('char_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['entry_id', 'ordinal']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_cv_skills', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('studio_cv_structures')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('canonical_name');
            $table->string('raw_name')->nullable();
            $table->string('nature', 16)->default('hard');
            $table->string('evidence', 16)->default('list_only');
            $table->unsignedBigInteger('evidence_bullet_id')->nullable();
            $table->decimal('years', 4, 1)->nullable();
            $table->unsignedInteger('first_ordinal')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['structure_id', 'canonical_name']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_cv_skills');
        Schema::dropIfExists('studio_cv_bullets');
        Schema::dropIfExists('studio_cv_entries');
        Schema::dropIfExists('studio_cv_structures');
    }
};
