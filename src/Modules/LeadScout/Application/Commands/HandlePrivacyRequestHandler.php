<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\PrivacyRequestOutcome;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutPrivacyRequestEloquentModel;

/**
 * Data-subject rights (spec FR-29, T073): search/export/erase/object by a
 * name or email fragment (min 3 chars, enforced by the command). Every
 * request is recorded in `scout_privacy_requests` with a subject hash —
 * never PII. Exported JSON (the subject's own data) goes to a file outside
 * the git-tracked tree.
 */
final readonly class HandlePrivacyRequestHandler
{
    /**
     * @return list<array{uuid: string, company: string, role: ?string, anonymized: bool}>
     */
    #[\NoDiscard]
    public function search(string $query): array
    {
        $rows = $this->matches($query);
        $this->ledger($query, PrivacyRequestType::Access);

        return $rows->map(static fn (ScoutContactEloquentModel $contact): array => [
            'uuid' => $contact->uuid,
            'company' => (string) $contact->company->canonical_domain,
            'role' => $contact->role_title,
            'anonymized' => $contact->anonymized_at !== null,
        ])->all();
    }

    /**
     * @return array{file: string, records: int}
     */
    #[\NoDiscard]
    public function export(string $query, string $outputPath): array
    {
        $rows = $this->matches($query);

        $payload = $rows->map(static fn (ScoutContactEloquentModel $contact): array => [
            'uuid' => $contact->uuid,
            'company' => (string) $contact->company->canonical_domain,
            'full_name' => $contact->full_name,
            'role_title' => $contact->role_title,
            'role_category' => $contact->role_category?->value,
            'published_email' => $contact->published_email,
            'public_profile_url' => $contact->public_profile_url,
            'evidence_url' => $contact->evidence_url,
            'anonymized_at' => $contact->anonymized_at,
        ])->all();

        $dir = dirname($outputPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->ledger($query, PrivacyRequestType::Access);

        return ['file' => $outputPath, 'records' => count($payload)];
    }

    /**
     * @return array{anonymized: int}
     */
    #[\NoDiscard]
    public function erase(string $query): array
    {
        $rows = $this->matches($query);
        $count = 0;

        DB::transaction(function () use ($rows, &$count): void {
            foreach ($rows as $contact) {
                if ($contact->anonymized_at === null) {
                    $this->anonymize($contact);
                    $count++;
                }
            }
        });

        $this->ledger($query, PrivacyRequestType::Erasure);

        return ['anonymized' => $count];
    }

    /**
     * @return array{anonymized: int, objections: int}
     */
    #[\NoDiscard]
    public function object(string $query): array
    {
        $rows = $this->matches($query);
        $anonymized = 0;
        $objections = 0;

        DB::transaction(function () use ($rows, &$anonymized, &$objections): void {
            foreach ($rows as $contact) {
                $name = (string) $contact->full_name;
                $domain = (string) $contact->company->canonical_domain;

                if ($name !== '') {
                    $hash = DecisionMakerExtractor::personHash($name, $domain);
                    $created = ScoutContactObjectionEloquentModel::query()->firstOrCreate(['person_hash' => $hash]);
                    if ($created->wasRecentlyCreated) {
                        $objections++;
                    }
                }

                if ($contact->anonymized_at === null) {
                    $this->anonymize($contact);
                    $anonymized++;
                }
            }
        });

        $this->ledger($query, PrivacyRequestType::Objection);

        return ['anonymized' => $anonymized, 'objections' => $objections];
    }

    /**
     * @return Collection<int, ScoutContactEloquentModel>
     */
    private function matches(string $query): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($query)).'%';

        return ScoutContactEloquentModel::query()
            ->with('company:id,canonical_domain')
            ->where(static function ($where) use ($like): void {
                $where->where('full_name', 'like', $like)
                    ->orWhere('published_email', 'like', $like);
            })
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    private function anonymize(ScoutContactEloquentModel $contact): void
    {
        $contact->update([
            'full_name' => null,
            'published_email' => null,
            'public_profile_url' => null,
            'evidence_excerpt' => null,
            'anonymized_at' => now(),
        ]);
    }

    private function ledger(string $query, PrivacyRequestType $type): void
    {
        ScoutPrivacyRequestEloquentModel::query()->create([
            'subject_ref' => hash('sha256', mb_strtolower(trim($query))),
            'request_type' => $type->value,
            'received_at' => now(),
            'resolved_at' => now(),
            'outcome' => PrivacyRequestOutcome::Resolved->value,
        ]);
    }
}
