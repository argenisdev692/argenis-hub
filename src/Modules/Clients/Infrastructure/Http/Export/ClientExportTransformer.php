<?php

declare(strict_types=1);

namespace Modules\Clients\Infrastructure\Http\Export;

use Modules\Clients\Domain\Enums\ClientStatus;
use Modules\Clients\Infrastructure\Http\Controllers\AdminClientController;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Shared\Infrastructure\Support\PhoneFormatter;

/**
 * Maps a {@see ClientEloquentModel} row to export columns, shared by the CSV,
 * Excel and PDF branches of {@see AdminClientController::export} so all three
 * stay identical. The module ships only this row transform — the writer /
 * streamer / PDF renderer live behind the Shared `ExportPort` (BACKEND-PHP §8).
 *
 * `Lifecycle` carries the CRM {@see ClientStatus};
 * `Status` is the soft-delete axis (`Active` / `Suspended`, BACKEND-PHP §8).
 */
final readonly class ClientExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Name', 'Email', 'Phone', 'Lifecycle', 'Owner', 'Created', 'Status'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(ClientEloquentModel $client): array
    {
        return $client
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(ClientEloquentModel $client): array
    {
        return [
            'Name' => $client->client_name,
            'Email' => $client->email,
            'Phone' => PhoneFormatter::national($client->phone),
            'Lifecycle' => $client->status->label(),
            'Owner' => self::ownerName($client),
            'Created' => $client->created_at?->toIso8601String(),
            'Status' => $client->deleted_at !== null ? 'Suspended' : 'Active',
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

    private static function ownerName(ClientEloquentModel $client): ?string
    {
        $owner = $client->relationLoaded('user') ? $client->user : null;

        if ($owner === null) {
            return null;
        }

        return trim(($owner->first_name ?? '').' '.($owner->last_name ?? '')) ?: null;
    }
}
