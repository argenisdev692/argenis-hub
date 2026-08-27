<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the optional owner FK. Null for anonymous landing-page submissions;
     * set to the acting user when a signed-in visitor submits the public form
     * or an operator logs a request from the admin UI.
     */
    public function up(): void
    {
        Schema::table('contact_supports', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_supports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
