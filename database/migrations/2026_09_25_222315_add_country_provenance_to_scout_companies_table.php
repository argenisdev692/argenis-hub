<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits "which country is this company in" from "which search found it".
 *
 * Until now `country` was written at discovery time from the country of the
 * planned query, and `timezone_overlap_hours` was derived from that value. A
 * company surfaced by a Portuguese search was therefore recorded as Portuguese
 * whatever its site said — a real run labelled a 150-person India company (Tuvoc,
 * +91 phone, "in India & USA" across its page titles) as `PT`, a Hungarian
 * company with a `.hu` domain and an `Kft.` legal form as `PT`, and a Polish
 * company with a `.pl` domain as `ES`.
 *
 * That is not only a display error: `timezone_overlap_hours` feeds the
 * `geo_contract` (weight 10) and `remote` (weight 5) subscores, so the lead
 * score itself was computed from a country nobody had verified.
 *
 * `country` becomes the verified value (nullable = unknown, never a guess).
 * `country_origin` keeps the search provenance for audit, and `country_source`
 * records WHICH signal produced the verified value, so an auditor can tell a
 * fact from an inference without re-reading the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scout_companies', function (Blueprint $table): void {
            $table->string('country_source')->nullable()->after('country');
            $table->string('country_origin')->nullable()->after('country_source');
            $table->string('country_evidence_url')->nullable()->after('country_origin');
        });
    }

    public function down(): void
    {
        Schema::table('scout_companies', function (Blueprint $table): void {
            $table->dropColumn(['country_source', 'country_origin', 'country_evidence_url']);
        });
    }
};
