<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vector storage (CHG-7, CHG-19): native `vector(1536)` on PostgreSQL, a text
 * stand-in on SQLite so the suite stays portable. The HNSW index lands in
 * T-121; similarity queries must SET LOCAL hnsw.iterative_scan = strict_order
 * (T-000 proves why).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->string('model', 64);
            $table->unsignedSmallInteger('dims');
            $table->char('content_hash', 64);

            if (DB::getDriverName() === 'pgsql') {
                $table->vector('embedding', dimensions: 1536);
            } else {
                $table->text('embedding');
            }

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'model'], 'studio_embeddings_owner_model_unique');
            $table->index(['user_id', 'owner_type']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_embeddings');
    }
};
