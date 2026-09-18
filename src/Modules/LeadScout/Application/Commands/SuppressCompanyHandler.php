<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\SuppressData;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Manual suppression (spec FR-17, T029). Idempotent: re-suppressing the
 * same domain returns the existing row.
 */
final readonly class SuppressCompanyHandler
{
    public function handle(SuppressData $data): ScoutSuppressionEloquentModel
    {
        // Bare domains travel the same normalization as posting URLs
        // (scheme assumed, `www.` stripped) so `WWW.Acme.ES` and
        // `https://acme.es` suppress the same company exactly once.
        $input = trim($data->domain);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) !== 1) {
            $input = 'https://'.$input;
        }

        try {
            $domain = CanonicalDomain::fromUrl($input)->value;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['domain' => $e->getMessage()]);
        }

        return DB::transaction(static function () use ($data, $domain): ScoutSuppressionEloquentModel {
            $existing = ScoutSuppressionEloquentModel::query()->where('canonical_domain', $domain)->first();

            if ($existing !== null) {
                return $existing;
            }

            return ScoutSuppressionEloquentModel::query()->create([
                'canonical_domain' => $domain,
                'source' => SuppressionSource::Manual->value,
                'reason' => mb_substr(trim($data->reason), 0, 500),
            ]);
        });
    }
}
