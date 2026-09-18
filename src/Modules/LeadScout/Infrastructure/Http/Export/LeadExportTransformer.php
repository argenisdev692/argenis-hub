<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Export;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Row → report columns (BACKEND-PHP §8): pipe chain extract → formatDates →
 * sanitize, `F j, Y` dates.
 *
 * One documented deviation from §8: no `Active`/`Suspended` status column.
 * `scout_*` rows are never soft-deleted (suppression + tier are the
 * lifecycle, spec US-4/FR-43), so Tier (leads) and Stage (funnel) are the
 * honest lifecycle columns — a fabricated Active/Suspended would lie.
 */
final readonly class LeadExportTransformer
{
    /**
     * @return array{Name: string, Domain: string, Country: string, Type: string, Origin: string, Tier: string, Score: string, Confidence: string, NeedsResearch: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformCompanyForExcel(ScoutCompanyEloquentModel $company): array
    {
        return $company
            |> self::extractCompany(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Name: string, Domain: string, Country: string, Type: string, Origin: string, Tier: string, Score: string, Confidence: string, NeedsResearch: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformCompanyForPdf(ScoutCompanyEloquentModel $company): array
    {
        return self::transformCompanyForExcel($company);
    }

    /**
     * @return array{Company: string, Stage: string, Medium: string, Kind: string, SentAt: string, Variant: string, Opportunities: string, Hours: string, Amount: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformOutreachForExcel(ScoutOutreachEloquentModel $outreach): array
    {
        return $outreach
            |> self::extractOutreach(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Company: string, Stage: string, Medium: string, Kind: string, SentAt: string, Variant: string, Opportunities: string, Hours: string, Amount: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformOutreachForPdf(ScoutOutreachEloquentModel $outreach): array
    {
        return self::transformOutreachForExcel($outreach);
    }

    /**
     * @return array{Name: string, Domain: string, Country: string, Type: string, Origin: string, Tier: string, Score: string, Confidence: string, NeedsResearch: string, Created: string}
     */
    private static function extractCompany(ScoutCompanyEloquentModel $company): array
    {
        $current = $company->relationLoaded('scoreResults')
            ? $company->scoreResults->firstWhere('is_current', true)
            : null;

        return [
            'Name' => $company->name,
            'Domain' => $company->canonical_domain,
            'Country' => (string) $company->country,
            'Type' => $company->company_type?->value ?? '—',
            'Origin' => $company->origin->value,
            'Tier' => $current?->tier->value ?? '—',
            'Score' => $current === null ? '—' : (string) $current->lead_score,
            'Confidence' => $current === null ? '—' : (string) $current->confidence,
            'NeedsResearch' => $company->needs_research ? 'Yes' : 'No',
            'Created' => $company->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @return array{Company: string, Stage: string, Medium: string, Kind: string, SentAt: string, Variant: string, Opportunities: string, Hours: string, Amount: string, Created: string}
     */
    private static function extractOutreach(ScoutOutreachEloquentModel $outreach): array
    {
        $opportunities = $outreach->relationLoaded('opportunities') ? $outreach->opportunities : collect();
        $hours = $opportunities->sum('hours_per_month');
        $amount = $opportunities->sum('amount_cents');

        return [
            'Company' => $outreach->relationLoaded('company') ? (string) $outreach->company->name : '—',
            'Stage' => $outreach->stage->value,
            'Medium' => $outreach->send_medium?->value ?? '—',
            'Kind' => $outreach->outreach_kind?->value ?? '—',
            'SentAt' => $outreach->sent_at?->toIso8601String() ?? '',
            'Variant' => $outreach->variant?->value ?? '—',
            'Opportunities' => (string) $opportunities->count(),
            'Hours' => (string) $hours,
            'Amount' => $amount > 0 ? number_format($amount / 100, 2).' EUR' : '—',
            'Created' => $outreach->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private static function formatDates(array $data): array
    {
        foreach (['Created', 'SentAt'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== '—') {
                try {
                    $data[$field] = (new \DateTimeImmutable($data[$field]))->format('F j, Y');
                } catch (\Exception) {
                    // Keep original value if parsing fails.
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }
}
