<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Entities\Signal;

/**
 * Everything a draft is grounded on (spec US-5): a contactable Tier A/B
 * company with its evidence, decisors, active channels and the operator's
 * current profile.
 */
final readonly class DraftContext
{
    /**
     * @param  list<Signal>  $signals  highest confidence first
     * @param  list<Contact>  $contacts  live (non-anonymized) decisors
     * @param  list<ContactChannel>  $channels  active channels
     */
    public function __construct(
        public Company $company,
        public array $signals,
        public bool $hasOffer,
        public array $contacts,
        public array $channels,
        public ?Profile $profile,
    ) {}

    /**
     * @return list<string>
     */
    public function signalKeys(): array
    {
        return array_map(static fn (Signal $signal): string => $signal->signalKey, $this->signals);
    }
}
