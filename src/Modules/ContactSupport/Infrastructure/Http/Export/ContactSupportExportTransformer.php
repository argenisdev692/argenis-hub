<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Export;

use Modules\ContactSupport\Infrastructure\Http\Controllers\AdminContactSupportController;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Shared\Infrastructure\Support\PhoneFormatter;

/**
 * Maps a {@see ContactSupportEloquentModel} row to export columns, shared by the
 * CSV, Excel and PDF branches of {@see AdminContactSupportController::export} so
 * all three stay identical. The module ships only this row transform — the
 * writer / streamer / PDF renderer live behind the Shared `ExportPort`
 * (BACKEND-PHP §8).
 *
 * `Status` is the soft-delete axis (`Active` / `Suspended`, BACKEND-PHP §8);
 * `Read` and `SMS Consent` are the request's own yes/no flags.
 */
final readonly class ContactSupportExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Name', 'Email', 'Phone', 'Subject', 'Read', 'SMS Consent', 'Spam', 'Created', 'Status'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(ContactSupportEloquentModel $support): array
    {
        return $support
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(ContactSupportEloquentModel $support): array
    {
        return [
            'Name' => trim($support->first_name.' '.$support->last_name),
            'Email' => $support->email,
            'Phone' => PhoneFormatter::national($support->phone),
            'Subject' => $support->subject,
            'Read' => $support->readed ? 'Yes' : 'No',
            'SMS Consent' => $support->sms_consent ? 'Yes' : 'No',
            'Spam' => $support->is_spam ? 'Yes' : 'No',
            'Created' => $support->created_at?->toIso8601String(),
            'Status' => $support->deleted_at !== null ? 'Suspended' : 'Active',
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
        return array_map(static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value, $row);
    }
}
