<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-skill defensibility, as a shortlist FILTER — not a score input.
 *
 * The candidate is rarely uniformly strong: four years of Laravel with a
 * frontend still on its second or third year is the real shape of this profile,
 * and a job whose core requirement is the weak half is worth skipping even
 * though it would score mid-band.
 *
 * The shape is `{skill: level}` with level 0-4:
 *
 *   0 absent · 1 junior · 2 mid · 3 solid · 4 anchor
 *
 * A posting is only shortlisted when at least one of its required/hard
 * requirements names a skill at or above the level the candidate declared for
 * it. `{}` (the default, and the value for every profile created before this
 * migration) means "no opinion" and the gate stays silent — a missing
 * preference must never start blocking postings.
 *
 * Deliberately NOT wired into `SkillRelationSet::creditFor()`: that feeds the
 * score, and the score is calibrated (SC-2 recomputes identically from stored
 * data, SC-3 holds the band against a retained hand-judged set). Changing what a
 * credit is worth would invalidate both. A filter can only remove candidates
 * from consideration, which is a different and reversible kind of decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_profiles', function (Blueprint $table): void {
            $table->json('skill_floors')->nullable()->after('stack_reject');
        });
    }

    public function down(): void
    {
        Schema::table('studio_profiles', function (Blueprint $table): void {
            $table->dropColumn('skill_floors');
        });
    }
};
