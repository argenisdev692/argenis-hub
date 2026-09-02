<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly availability template. Multiple rows per `day_of_week` are allowed
     * (no unique constraint) so a day can carry a split shift, e.g. the standard
     * Portuguese 09:00-13:00 + 14:00-18:00 with a lunch break.
     */
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday … 6 = Saturday (Carbon-aligned)
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // Resolver looks up open slots by day; list defaults to status + order.
            $table->index(['day_of_week', 'is_available']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
