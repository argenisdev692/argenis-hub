<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('disk', 64);
            $table->string('path')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 16)->default('completed');
            $table->string('connection', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // One index row per archive file. Failed attempts carry a null path;
            // SQL treats nulls as distinct, so many failed rows never collide.
            $table->unique(['disk', 'path']);
            // Canonical list filter pattern: status filter + created_at ordering.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
