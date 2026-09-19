<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\LeadScout\Application\DTOs\LeadFilterData;
use Modules\LeadScout\Infrastructure\Http\Export\LeadExportTransformer;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered bandeja (`dataset=leads`) or funnel
 * (`dataset=funnel`) as CSV / XLSX / PDF (spec FR-21, plan §5). Both reuse
 * `LeadFilterData` + `scopeApplyFilters` so list and export never drift.
 */
final readonly class LeadExportController
{
    /** @var list<string> */
    private const array LEAD_HEADERS = ['Name', 'Domain', 'Country', 'Type', 'Origin', 'Tier', 'Score', 'Confidence', 'NeedsResearch', 'Created'];

    /** @var list<string> */
    private const array FUNNEL_HEADERS = ['Company', 'Stage', 'Medium', 'Kind', 'SentAt', 'Variant', 'Opportunities', 'Hours', 'Amount', 'Created'];

    public function __construct(private ExportPort $export) {}

    #[QueryParameter('dataset', description: 'Export dataset: `leads` (bandeja) or `funnel` (outreaches + stages + deals).', type: 'string', default: 'leads', example: 'leads')]
    #[QueryParameter('format', description: 'Export file format: `csv`, `xlsx` or `pdf`.', type: 'string', default: 'csv', example: 'xlsx')]
    public function __invoke(Request $request, LeadFilterData $filters): StreamedResponse|Response
    {
        /** @var array{dataset?: string, format?: string} $validated */
        $validated = $request->validate([
            'dataset' => ['sometimes', 'string', Rule::in(['leads', 'funnel'])],
            'format' => ['sometimes', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);

        $dataset = $validated['dataset'] ?? 'leads';
        $format = $validated['format'] ?? 'csv';

        return match ($dataset) {
            'funnel' => $this->funnel($filters, $format),
            default => $this->leads($filters, $format),
        };
    }

    private function leads(LeadFilterData $filters, string $format): StreamedResponse|Response
    {
        $rows = ScoutCompanyEloquentModel::query()
            ->applyFilters($filters->toCriteria())
            ->with(['scoreResults' => fn (HasMany $query): HasMany => $query
                ->where('is_current', true)
                ->select(['id', 'company_id', 'tier', 'lead_score', 'confidence', 'is_current'])])
            ->select(['id', 'uuid', 'canonical_domain', 'name', 'country', 'origin', 'company_type', 'needs_research', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'lead-scout-leads.pdf',
                'exports.pdf.lead_scout_leads',
                [
                    'rows' => $rows->map(LeadExportTransformer::transformCompanyForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "lead-scout-leads.{$format}",
                self::LEAD_HEADERS,
                $rows->map(LeadExportTransformer::transformCompanyForExcel(...)),
            ),
        };
    }

    private function funnel(LeadFilterData $filters, string $format): StreamedResponse|Response
    {
        // Subquery, not pluck(): the filtered id set never lands in PHP memory.
        $companyIds = ScoutCompanyEloquentModel::query()
            ->applyFilters($filters->toCriteria())
            ->select('id');

        $rows = ScoutOutreachEloquentModel::query()
            ->whereIn('company_id', $companyIds)
            ->with(['company:id,name', 'opportunities:id,outreach_id,hours_per_month,amount_cents'])
            ->select(['id', 'uuid', 'company_id', 'stage', 'send_medium', 'outreach_kind', 'sent_at', 'variant', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'lead-scout-funnel.pdf',
                'exports.pdf.lead_scout_funnel',
                [
                    'rows' => $rows->map(LeadExportTransformer::transformOutreachForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "lead-scout-funnel.{$format}",
                self::FUNNEL_HEADERS,
                $rows->map(LeadExportTransformer::transformOutreachForExcel(...)),
            ),
        };
    }
}
