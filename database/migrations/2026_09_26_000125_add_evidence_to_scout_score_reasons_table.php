<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-7 as a DB constraint (spec fix): every score reason stores its
        // own evidence. A phantom +points row with no URL/excerpt could be
        // saved before; after this migration the database rejects it.
        Schema::table('scout_score_reasons', function (Blueprint $table): void {
            $table->string('evidence_url', 2048)->nullable();
            $table->text('evidence_excerpt')->nullable();
        });

        // Backfill from the linked signal's evidence (driver-agnostic PHP
        // loop: SQLite cannot UPDATE..JOIN).
        DB::table('scout_score_reasons')->whereNotNull('signal_id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $signal = DB::table('scout_signals')->where('id', $row->signal_id)->first(['evidence_url', 'evidence_excerpt']);

                if ($signal !== null
                    && is_string($signal->evidence_url) && $signal->evidence_url !== ''
                    && is_string($signal->evidence_excerpt) && $signal->evidence_excerpt !== '') {
                    DB::table('scout_score_reasons')->where('id', $row->id)->update([
                        'evidence_url' => $signal->evidence_url,
                        'evidence_excerpt' => $signal->evidence_excerpt,
                    ]);
                }
            }
        });

        // Phantoms: no linked signal, or a signal without evidence. They can
        // never answer «¿por qué 87?» — delete them instead of keeping points
        // nobody can explain.
        DB::table('scout_score_reasons')
            ->whereNull('evidence_url')
            ->orWhere('evidence_url', '')
            ->whereNull('evidence_excerpt')
            ->orWhere('evidence_excerpt', '')
            ->delete();

        // Native Laravel 13 column change (no doctrine/dbal): NOT NULL from
        // here on, on BOTH drivers. SQLite rebuilds the table (safe here: the
        // table carries no partial/functional index, only a plain one).
        Schema::table('scout_score_reasons', function (Blueprint $table): void {
            $table->string('evidence_url', 2048)->nullable(false)->change();
            $table->text('evidence_excerpt')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('scout_score_reasons', function (Blueprint $table): void {
            $table->string('evidence_url', 2048)->nullable()->change();
            $table->text('evidence_excerpt')->nullable()->change();
        });

        Schema::table('scout_score_reasons', function (Blueprint $table): void {
            $table->dropColumn(['evidence_url', 'evidence_excerpt']);
        });
    }
};
