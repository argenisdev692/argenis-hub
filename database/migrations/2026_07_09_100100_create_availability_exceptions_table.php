<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date-specific overrides of the weekly template. `is_available = false` is a
     * closure (holiday, one-off shutdown); `is_available = true` is a forced-open
     * day carrying its OWN hours (`start_time`/`end_time`). An exception row always
     * OVERRIDES a computed national holiday for that date.
     */
    public function up(): void
    {
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->date('date');
            $table->boolean('is_available')->default(false);
            $table->time('start_time')->nullable(); // required only when is_available = true
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();

            // Distinguishes system-materialised national holidays (`holiday`) from
            // user-created date overrides (`manual`). Only `holiday` rows are purged
            // and rebuilt on a country change / yearly sync — manual overrides are
            // never touched.
            $table->string('source')->default('manual');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['deleted_at', 'date']);
        });

        // One ACTIVE exception per date. Soft-deleted rows are ignored so a date
        // can be re-created after a prior override was removed. Partial unique
        // indexes aren't expressible via the Blueprint, hence the raw statement.
        DB::statement(
            'CREATE UNIQUE INDEX availability_exceptions_date_active
             ON availability_exceptions (date) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
