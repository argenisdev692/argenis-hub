<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Application\DTOs\UpsertContactData;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Enums\RoleCategory;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Exceptions\PersonOpposedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Domain\ValueObjects\ContactDetails;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;

/**
 * Manual decisor upsert (spec US-11, T049): the operator adds by hand what
 * extraction could not find. Excluded/ambiguous titles → 422; emails
 * outside the company domain → 422; opposed people → 409.
 */
final readonly class UpsertContactHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private ContactRepositoryPort $contacts,
    ) {}

    public function handleByCompany(string $companyUuid, UpsertContactData $data): Contact
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        $this->assertImportable($company->canonicalDomain, $data);

        return $this->contacts->createManual($company->id, self::details($data), CarbonImmutable::now());
    }

    public function handleUpdate(string $contactUuid, UpsertContactData $data): Contact
    {
        $contact = $this->contacts->byUuid($contactUuid) ?? throw new ContactNotFoundException($contactUuid);

        $this->assertImportable((string) $contact->companyDomain, $data);

        return $this->contacts->updateDetails($contact, self::details($data), CarbonImmutable::now());
    }

    private static function details(UpsertContactData $data): ContactDetails
    {
        return new ContactDetails(
            fullName: mb_substr(trim($data->fullName), 0, 255),
            roleTitle: mb_substr(trim($data->roleTitle), 0, 120),
            roleCategory: RoleCategory::from($data->roleCategory),
            isPrimary: $data->isPrimary,
            publishedEmail: $data->publishedEmail,
            publicProfileUrl: $data->publicProfileUrl,
        );
    }

    private function assertImportable(string $companyDomain, UpsertContactData $data): void
    {
        if (RoleTaxonomy::classify($data->roleTitle) === null) {
            throw InvalidInputException::withMessages([
                'role_title' => 'This title is excluded or ambiguous: only decisor titles are stored.',
            ]);
        }

        if ($data->publishedEmail !== null) {
            $parts = explode('@', mb_strtolower(trim($data->publishedEmail)));

            if (count($parts) !== 2 || $parts[1] !== mb_strtolower($companyDomain)) {
                throw InvalidInputException::withMessages([
                    'published_email' => 'Only emails published on the company domain are stored; never guessed ones.',
                ]);
            }
        }

        if ($this->contacts->isPersonOpposed(DecisionMakerExtractor::personHash($data->fullName, $companyDomain))) {
            throw new PersonOpposedException;
        }
    }
}
