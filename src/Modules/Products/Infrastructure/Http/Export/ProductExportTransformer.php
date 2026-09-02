<?php

declare(strict_types=1);

namespace Modules\Products\Infrastructure\Http\Export;

use Modules\Products\Infrastructure\Http\Controllers\AdminProductController;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

/**
 * Maps a {@see ProductEloquentModel} row to export columns, shared by the CSV,
 * Excel and PDF branches of {@see AdminProductController::export} so all three
 * stay identical. The module ships only this row transform — the writer /
 * streamer / PDF renderer live behind the Shared `ExportPort` (BACKEND-PHP §8).
 *
 * `Catalog` carries the publication state (Draft / Published / Archived);
 * `Status` is the soft-delete axis (`Active` / `Suspended`, BACKEND-PHP §8).
 */
final readonly class ProductExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Title', 'Type', 'Client', 'Price', 'Unit', 'Hours', 'Sessions', 'Modality', 'Catalog', 'Created', 'Status'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(ProductEloquentModel $product): array
    {
        return $product
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(ProductEloquentModel $product): array
    {
        return [
            'Title' => $product->title,
            'Type' => self::titleize($product->type->value),
            'Client' => self::clientName($product),
            'Price' => number_format((float) $product->price, 2, '.', '').' '.$product->currency,
            'Unit' => self::titleize($product->default_unit->value),
            'Hours' => $product->total_hours !== null
                ? number_format((float) $product->total_hours, 2, '.', '')
                : null,
            'Sessions' => $product->total_sessions !== null ? (string) $product->total_sessions : null,
            'Modality' => $product->modality,
            'Catalog' => self::titleize($product->status->value),
            'Created' => $product->created_at?->toIso8601String(),
            'Status' => $product->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * ISO8601 → "March 3, 2026 14:05" (BACKEND-PHP §8 export date rule).
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private static function formatDates(array $row): array
    {
        if (is_string($row['Created']) && $row['Created'] !== '') {
            try {
                $row['Created'] = (new \DateTimeImmutable($row['Created']))->format('F j, Y H:i');
            } catch (\Exception) {
                // keep the original value when parsing fails
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string>
     */
    private static function sanitize(array $row): array
    {
        return array_map(
            static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value,
            $row,
        );
    }

    /** `VIDEO_COURSE` → `Video Course`. */
    private static function titleize(string $value): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $value)));
    }

    private static function clientName(ProductEloquentModel $product): ?string
    {
        return $product->relationLoaded('client') ? $product->client?->client_name : null;
    }
}
