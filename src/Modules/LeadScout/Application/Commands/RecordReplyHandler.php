<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\RecordReplyData;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Reply record with absolute-unsubscribe semantics (spec FR-42/FR-43,
 * T084): `interested → positive`, `not_interested → lost`, `unsubscribe →
 * do_not_contact` + company suppression on every channel (+ the person's
 * opposition when the mailbox was nominative). After an unsubscribe, no
 * entry — discovery, import, manual, offers, re-score — may propose the
 * company again, and no web route lifts it.
 */
final readonly class RecordReplyHandler
{
    public function __construct(private OutreachRepositoryPort $outreaches) {}

    public function handle(string $outreachUuid, RecordReplyData $data, int $operatorId): ScoutOutreachEloquentModel
    {
        $outreach = $this->outreaches->findByUuid($outreachUuid);

        if ($outreach === null) {
            throw ValidationException::withMessages(['outreach' => 'Outreach not found.']);
        }

        if ($outreach->stage !== OutreachStage::Sent) {
            throw ValidationException::withMessages(['stage' => 'Only a sent outreach can receive a reply.']);
        }

        $outcome = ReplyOutcome::from($data->outcome);

        $to = match ($outcome) {
            ReplyOutcome::Interested => OutreachStage::Positive,
            ReplyOutcome::NotInterested => OutreachStage::Lost,
            ReplyOutcome::Unsubscribe => OutreachStage::DoNotContact,
        };

        return DB::transaction(function () use ($outreach, $data, $operatorId, $outcome, $to): ScoutOutreachEloquentModel {
            $outreach->loadMissing(['company', 'contact']);
            if ($data->repliedAt !== null) {
                try {
                    $outreach->update(['replied_at' => CarbonImmutable::parse($data->repliedAt)->toDateTimeString()]);
                } catch (\Exception) {
                    throw ValidationException::withMessages(['replied_at' => 'Invalid date.']);
                }
            }

            if ($data->notes !== null) {
                $outreach->update(['notes' => mb_substr(trim($data->notes), 0, 2000)]);
            }

            $moved = $this->outreaches->moveToStage($outreach->refresh(), $to, $operatorId, $outcome);

            if ($outcome === ReplyOutcome::Unsubscribe) {
                $this->suppress($moved);
            }

            return $moved;
        });
    }

    private function suppress(ScoutOutreachEloquentModel $outreach): void
    {
        $company = $outreach->company;

        ScoutSuppressionEloquentModel::query()->firstOrCreate(
            ['canonical_domain' => $company->canonical_domain],
            [
                'source' => SuppressionSource::Objection->value,
                'reason' => 'Unsubscribe — absolute precedence (FR-43).',
            ],
        );

        $contact = $outreach->contact;

        if ($contact instanceof ScoutContactEloquentModel
            && $contact->email_kind === EmailKind::Nominative
            && $contact->published_email !== null) {
            (new ObjectContactHandler)->handle($contact->uuid);
        }
    }
}
