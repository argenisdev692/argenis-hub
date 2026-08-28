<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_media', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->foreignId('portfolio_id')->constrained('portfolios')->onUpdate('cascade')->onDelete('cascade');
            $table->string('path', 500);
            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // Gallery render order per portfolio.
            $table->index(['portfolio_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_media');
    }
};
