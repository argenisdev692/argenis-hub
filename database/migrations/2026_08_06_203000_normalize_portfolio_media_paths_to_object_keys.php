<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy portfolio rows stored absolute R2 public URLs in cover_path / video_path
 * (and gallery path). Accessors then called StoragePort::publicUrl(), which
 * prepended R2_URL again → double-host URLs. Convert absolute URLs to object
 * keys so cover_url / video_url resolve via the configured R2_URL once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeColumn('portfolios', 'cover_path');
        $this->normalizeColumn('portfolios', 'video_path');
        $this->normalizeColumn('portfolio_media', 'path');
    }

    public function down(): void
    {
        // Irreversible: absolute hosts cannot be reconstructed safely after
        // stripping (R2_URL may differ from the original public base).
    }

    private function normalizeColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $raw = $row->{$column};
                    if (! is_string($raw) || $raw === '') {
                        continue;
                    }

                    $key = $this->toObjectKey($raw);
                    if ($key === $raw) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => $key]);
                }
            });
    }

    private function toObjectKey(string $value): string
    {
        if (! str_starts_with($value, 'https://') && ! str_starts_with($value, 'http://')) {
            return $value;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        return ltrim(rawurldecode($path), '/');
    }
};
