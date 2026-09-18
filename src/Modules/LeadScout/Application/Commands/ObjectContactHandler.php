<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\PrivacyRequestOutcome;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutPrivacyRequestEloquentModel;

/**
 * Person objection (spec US-11 CA-10, FR-25/29, T049): immediate
 * anonymization + opposition hash (blocks re-extraction) + privacy-ledger
 * row without personal data.
 */
final readonly class ObjectContactHandler
{
    public function handle(string $contactUuid): void
    {
        $contact = ScoutContactEloquentModel::query()->with('company')->where('uuid', $contactUuid)->first()
            ?? throw new ContactNotFoundException($contactUuid);

        DB::transaction(function () use ($contact): void {
            $hash = DecisionMakerExtractor::personHash(
                (string) $contact->full_name,
                (string) $contact->company->canonical_domain,
            );

            ScoutContactObjectionEloquentModel::query()->firstOrCreate(['person_hash' => $hash]);

            $contact->update([
                'full_name' => null,
                'published_email' => null,
                'public_profile_url' => null,
                'evidence_excerpt' => null,
                'anonymized_at' => now(),
            ]);

            ScoutPrivacyRequestEloquentModel::query()->create([
                'subject_ref' => $hash,
                'request_type' => PrivacyRequestType::Objection->value,
                'received_at' => now(),
                'resolved_at' => now(),
                'outcome' => PrivacyRequestOutcome::Resolved->value,
            ]);
        });
    }
}
