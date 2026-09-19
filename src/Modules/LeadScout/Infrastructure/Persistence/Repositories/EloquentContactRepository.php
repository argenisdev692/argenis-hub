<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Enums\ContactSource;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\ContactDetails;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\ContactMapper;

final readonly class EloquentContactRepository implements ContactRepositoryPort
{
    private const string COMPANY = 'company:id,canonical_domain';

    public function byUuid(string $uuid): ?Contact
    {
        $model = ScoutContactEloquentModel::query()->with(self::COMPANY)->where('uuid', $uuid)->first();

        return $model === null ? null : ContactMapper::toEntity($model);
    }

    public function byId(int $id): ?Contact
    {
        $model = ScoutContactEloquentModel::query()->with(self::COMPANY)->find($id);

        return $model === null ? null : ContactMapper::toEntity($model);
    }

    public function createManual(int $companyId, ContactDetails $details, DateTimeImmutable $verifiedAt): Contact
    {
        return DB::transaction(static function () use ($companyId, $details, $verifiedAt): Contact {
            $model = ScoutContactEloquentModel::query()->create([
                'company_id' => $companyId,
                ...self::detailColumns($details),
                'is_primary' => $details->isPrimary ?? false,
                'source' => ContactSource::Manual->value,
                'last_verified_at' => $verifiedAt,
            ]);

            return ContactMapper::toEntity($model->load(self::COMPANY));
        });
    }

    public function updateDetails(Contact $contact, ContactDetails $details, DateTimeImmutable $verifiedAt): Contact
    {
        return DB::transaction(static function () use ($contact, $details, $verifiedAt): Contact {
            if ($details->isPrimary === true) {
                ScoutContactEloquentModel::query()
                    ->where('company_id', $contact->companyId)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }

            $model = ScoutContactEloquentModel::query()->findOrFail($contact->id);
            $model->update([
                ...self::detailColumns($details),
                'is_primary' => $details->isPrimary ?? $model->is_primary,
                'last_verified_at' => $verifiedAt,
            ]);

            return ContactMapper::toEntity($model->refresh()->load(self::COMPANY));
        });
    }

    public function anonymize(int $contactId, DateTimeImmutable $at): void
    {
        ScoutContactEloquentModel::query()->findOrFail($contactId)->update([
            'full_name' => null,
            'published_email' => null,
            'public_profile_url' => null,
            'evidence_excerpt' => null,
            'anonymized_at' => $at,
        ]);
    }

    public function matchingPerson(string $query, int $limit): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($query)).'%';

        return ScoutContactEloquentModel::query()
            ->with(self::COMPANY)
            ->where(static function (Builder $where) use ($like): void {
                $where->where('full_name', 'like', $like)
                    ->orWhere('published_email', 'like', $like);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(ContactMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function retentionCandidates(): iterable
    {
        // One query per page: the last touch is a correlated subquery, not a
        // per-contact lookup.
        $lastTouch = ScoutOutreachEloquentModel::query()
            ->selectRaw('max(coalesce(sent_at, stage_changed_at, created_at))')
            ->whereColumn('contact_id', 'scout_contacts.id');

        $rows = ScoutContactEloquentModel::query()
            ->select('scout_contacts.*')
            ->selectSub($lastTouch, 'last_touch_at')
            ->whereNull('anonymized_at')
            ->lazyById(200, 'scout_contacts.id', 'id');

        foreach ($rows as $model) {
            $lastTouchAt = $model->getAttribute('last_touch_at');

            yield [
                'contact' => ContactMapper::toEntity($model),
                'lastTouchAt' => $lastTouchAt === null ? null : CarbonImmutable::parse((string) $lastTouchAt)->toDateTimeImmutable(),
            ];
        }
    }

    public function liveForCompany(int $companyId): array
    {
        return ScoutContactEloquentModel::query()
            ->with(self::COMPANY)
            ->where('company_id', $companyId)
            ->whereNull('anonymized_at')
            ->orderBy('id')
            ->get()
            ->map(ContactMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function markVerified(int $contactId, DateTimeImmutable $at): void
    {
        ScoutContactEloquentModel::query()->whereKey($contactId)->update(['last_verified_at' => $at]);
    }

    public function markNotified(int $contactId, DateTimeImmutable $at): void
    {
        ScoutContactEloquentModel::query()->whereKey($contactId)->update(['notified_at' => $at]);
    }

    public function anonymizeCompany(int $companyId, DateTimeImmutable $at): int
    {
        return ScoutContactEloquentModel::query()
            ->where('company_id', $companyId)
            ->whereNull('anonymized_at')
            ->update([
                'full_name' => null,
                'published_email' => null,
                'public_profile_url' => null,
                'evidence_excerpt' => null,
                'anonymized_at' => $at,
            ]);
    }

    public function hasLiveContactNamed(int $companyId, string $fullName): bool
    {
        return ScoutContactEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('full_name', $fullName)
            ->whereNull('anonymized_at')
            ->exists();
    }

    public function createExtracted(int $companyId, array $candidate, bool $isPrimary, DateTimeImmutable $contactDeadline): void
    {
        ScoutContactEloquentModel::query()->create([
            'company_id' => $companyId,
            'full_name' => $candidate['name'],
            'role_title' => $candidate['title'],
            'role_category' => $candidate['category'],
            'is_primary' => $isPrimary,
            'published_email' => $candidate['email'],
            'email_kind' => $candidate['email_kind'],
            'public_profile_url' => $candidate['profile_url'],
            'source' => ContactSource::Website->value,
            'evidence_url' => $candidate['evidence_url'],
            'evidence_excerpt' => $candidate['excerpt'],
            'evidence_captured_at' => $candidate['captured_at'],
            'contact_deadline_at' => $contactDeadline,
            'last_verified_at' => $candidate['captured_at'],
        ]);
    }

    public function objectionHashes(): array
    {
        return ScoutContactObjectionEloquentModel::query()->pluck('person_hash')->all();
    }

    public function isPersonOpposed(string $personHash): bool
    {
        return ScoutContactObjectionEloquentModel::query()->where('person_hash', $personHash)->exists();
    }

    public function recordObjection(string $personHash): bool
    {
        return ScoutContactObjectionEloquentModel::query()->firstOrCreate(['person_hash' => $personHash])->wasRecentlyCreated;
    }

    /**
     * @return array<string, mixed>
     */
    private static function detailColumns(ContactDetails $details): array
    {
        return [
            'full_name' => $details->fullName,
            'role_title' => $details->roleTitle,
            'role_category' => $details->roleCategory->value,
            'published_email' => $details->publishedEmail,
            'email_kind' => $details->emailKind()?->value,
            'public_profile_url' => $details->publicProfileUrl,
        ];
    }
}
