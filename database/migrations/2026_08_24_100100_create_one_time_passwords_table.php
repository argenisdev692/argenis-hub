<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-one-time-passwords storage — backs the 6-digit codes used for
 * email verification (FR-02) and password reset (FR-11).
 *
 * Published from the package stub. Codes are short-lived (30 min), single-use
 * (deleted on consumption) and single-active-per-user, so no soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_time_passwords', function (Blueprint $table): void {
            $table->id();

            $table->string('password');
            $table->text('origin_properties')->nullable();

            $table->dateTime('expires_at')->index();
            $table->morphs('authenticatable');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
