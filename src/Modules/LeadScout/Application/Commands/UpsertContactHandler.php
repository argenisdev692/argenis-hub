<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\UpsertContactData;
use Modules\LeadScout\Domain\Enums\ContactSource;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\RoleCategory;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Exceptions\PersonOpposedException;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;

/**
 * Manual decisor upsert (spec US-11, T049): the operator adds by hand what
 * extraction could not find. Excluded/ambiguous titles → 422; emails
 * outside the company domain → 422; opposed people → 409.
 */
final readonly class UpsertContactHandler
{
    public function handleByCompany(string $companyUuid, UpsertContactData $data): ScoutContactEloquentModel
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        $this->assertImportable($company, $data);

        return DB::transaction(fn (): ScoutContactEloquentModel => ScoutContactEloquentModel::query()->create([
            'company_id' => $company->id,
            'full_name' => mb_substr(trim($data->fullName), 0, 255),
            'role_title' => mb_substr(trim($data->roleTitle), 0, 120),
            'role_category' => RoleCategory::from($data->roleCategory)->value,
            'is_primary' => $data->isPrimary ?? false,
            'published_email' => $data->publishedEmail,
            'email_kind' => $data->publishedEmail === null ? null : EmailKind::Nominative->value,
            'public_profile_url' => $data->publicProfileUrl,
            'source' => ContactSource::Manual->value,
            'last_verified_at' => now(),
        ]));
    }

    public function handleUpdate(string $contactUuid, UpsertContactData $data): ScoutContactEloquentModel
    {
        $contact = ScoutContactEloquentModel::query()->with('company')->where('uuid', $contactUuid)->first()
            ?? throw new ContactNotFoundException($contactUuid);

        $company = $contact->company;
        $this->assertImportable($company, $data);

        return DB::transaction(function () use ($contact, $company, $data): ScoutContactEloquentModel {
            if (($data->isPrimary ?? false) === true) {
                ScoutContactEloquentModel::query()
                    ->where('company_id', $company->id)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }

            $contact->update([
                'full_name' => mb_substr(trim($data->fullName), 0, 255),
                'role_title' => mb_substr(trim($data->roleTitle), 0, 120),
                'role_category' => RoleCategory::from($data->roleCategory)->value,
                'is_primary' => $data->isPrimary ?? $contact->is_primary,
                'published_email' => $data->publishedEmail,
                'email_kind' => $data->publishedEmail === null ? null : EmailKind::Nominative->value,
                'public_profile_url' => $data->publicProfileUrl,
                'last_verified_at' => now(),
            ]);

            return $contact->refresh();
        });
    }

    private function assertImportable(
        ScoutCompanyEloquentModel $company,
        UpsertContactData $data,
    ): void {
        if (RoleTaxonomy::classify($data->roleTitle) === null) {
            throw ValidationException::withMessages([
                'role_title' => 'This title is excluded or ambiguous: only decisor titles are stored.',
            ]);
        }

        if ($data->publishedEmail !== null) {
            $parts = explode('@', mb_strtolower(trim($data->publishedEmail)));

            if (count($parts) !== 2 || $parts[1] !== mb_strtolower($company->canonical_domain)) {
                throw ValidationException::withMessages([
                    'published_email' => 'Only emails published on the company domain are stored; never guessed ones.',
                ]);
            }
        }

        $hash = DecisionMakerExtractor::personHash($data->fullName, $company->canonical_domain);
        $opposed = ScoutContactObjectionEloquentModel::query()->where('person_hash', $hash)->exists();

        if ($opposed) {
            throw new PersonOpposedException;
        }
    }
}
