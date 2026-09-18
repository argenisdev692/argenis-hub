<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;

/**
 * Retention janitor (spec FR-27, T070): decisors uncontacted past their
 * 30-day deadline and contacted ones 12 months after the last interaction
 * are anonymized (cargo kept for metrics); page markdown older than 30
 * days is emptied (hash + forms summary kept, evidence lives on signals).
 *
 * @return array{contacts: int, pages: int}
 */
final readonly class PruneLeadContactsHandler
{
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $contacts = 0;

        ScoutContactEloquentModel::query()
            ->whereNull('anonymized_at')
            ->chunkById(200, function ($batch) use ($now, &$contacts): void {
                foreach ($batch as $contact) {
                    if ($this->isDue($contact, $now)) {
                        $contact->update([
                            'full_name' => null,
                            'published_email' => null,
                            'public_profile_url' => null,
                            'evidence_excerpt' => null,
                            'anonymized_at' => $now->toDateTimeString(),
                        ]);
                        $contacts++;
                    }
                }
            });

        $pages = ScoutFetchedPageEloquentModel::query()
            ->whereNotNull('content_markdown')
            ->where('fetched_at', '<', $now->subDays(30)->toDateTimeString())
            ->update(['content_markdown' => null, 'content_pruned_at' => $now->toDateTimeString()]);

        return ['contacts' => $contacts, 'pages' => (int) $pages];
    }

    private function isDue(ScoutContactEloquentModel $contact, CarbonImmutable $now): bool
    {
        $lastInteraction = $contact->outreaches()
            ->selectRaw('max(coalesce(sent_at, stage_changed_at, created_at)) as last_touch')
            ->value('last_touch');

        if ($lastInteraction !== null) {
            return CarbonImmutable::parse($lastInteraction)->lt($now->subMonths(12));
        }

        return $contact->contact_deadline_at !== null
            && CarbonImmutable::parse($contact->contact_deadline_at)->lt($now);
    }
}
