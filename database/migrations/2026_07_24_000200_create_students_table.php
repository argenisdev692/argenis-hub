<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('dni', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('avatar', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('DRAFT');
            $table->boolean('active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->index('deleted_at', 'idx_students_deleted_at');
            $table->index(['deleted_at', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
