<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Http\Export;

use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * Maps a CV row to CSV / Excel / PDF columns (BACKEND-PHP §8).
 */
final readonly class CvExportTransformer
{
    /**
     * @return array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}
     */
    #[\NoDiscard]
    public static function transformForTable(CvEloquentModel $cv): array
    {
        return $cv
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}
     */
    #[\NoDiscard]
    public static function transformForPdf(CvEloquentModel $cv): array
    {
        return self::transformForTable($cv);
    }

    /**
     * @return array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}
     */
    private static function extractBaseData(CvEloquentModel $cv): array
    {
        return [
            'Title' => $cv->title,
            'Niche' => $cv->niche->value,
            'Primary' => $cv->is_primary ? 'Yes' : 'No',
            'Type' => strtoupper($cv->file_type->value),
            'Filename' => $cv->original_filename,
            'Owner' => self::ownerLabel($cv),
            'Created' => $cv->created_at?->toIso8601String() ?? '',
            'Status' => $cv->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}  $data
     * @return array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}
     */
    private static function formatDates(array $data): array
    {
        if ($data['Created'] !== '') {
            try {
                $data['Created'] = (new \DateTimeImmutable($data['Created']))->format('F j, Y');
            } catch (\Exception) {
                // Keep original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}  $data
     * @return array{Title: string, Niche: string, Primary: string, Type: string, Filename: string, Owner: string, Created: string, Status: string}
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }

    private static function ownerLabel(CvEloquentModel $cv): string
    {
        $name = trim(sprintf('%s %s', $cv->user?->first_name ?? '', $cv->user?->last_name ?? ''));

        return $name !== '' ? $name : 'System';
    }
}
