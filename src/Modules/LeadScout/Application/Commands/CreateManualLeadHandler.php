<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\CreateManualLeadData;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

/**
 * Manual lead intake (spec FR-20, T029): company + URL + note for the
 * manual-prospecting phase and referrals. Suppressed domains answer 409 —
 * the gate prevails over manual entry (spec FR-43).
 */
final readonly class CreateManualLeadHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private SuppressionGate $gate,
    ) {}

    public function handle(CreateManualLeadData $data, int $userId): ScoutCompanyEloquentModel
    {
        try {
            $domain = CanonicalDomain::fromUrl(trim($data->url))->value;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        $this->assertNotSuppressed($domain, $data->name);

        $existing = $this->companies->findByDomain($domain);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(fn (): ScoutCompanyEloquentModel => $this->companies->create([
            'canonical_domain' => $domain,
            'name' => mb_substr(trim($data->name), 0, 255),
            'origin' => CompanyOrigin::Manual->value,
            'origin_ref' => $data->note === null ? null : mb_substr(trim($data->note), 0, 255),
        ]));
    }

    private function assertNotSuppressed(string $domain, string $name): void
    {
        $candidates = $this->companies->suppressionsMatching($domain, null, $name)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($domain, null, $name, $candidates)) {
            throw new SuppressedException;
        }
    }
}
