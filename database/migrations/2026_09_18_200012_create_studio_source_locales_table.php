<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_source_locales', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('studio_sources')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('country_code', 8);
            $table->string('host');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['source_id', 'country_code', 'host']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_source_locales');
    }
};
