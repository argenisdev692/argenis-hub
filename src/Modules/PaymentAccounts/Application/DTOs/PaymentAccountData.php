<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\DTOs;

use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of an issuer payment account.
 *
 * The allowlist behind every payment-account response: the auto-increment `id`
 * and `user_id` never cross this boundary (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PaymentAccountData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly PaymentMethod $method,
        public readonly ?string $currency,
        public readonly string $label,
        public readonly ?string $beneficiary,
        public readonly ?string $bankName,
        public readonly ?string $iban,
        public readonly ?string $bic,
        public readonly ?string $accountNumber,
        public readonly ?string $routingNumber,
        public readonly ?string $holderEmail,
        public readonly ?string $holderPhone,
        public readonly ?string $instructions,
        public readonly bool $isDefault,
        public readonly bool $isActive,
        public readonly int $sortOrder,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(PaymentAccountEloquentModel $account): self
    {
        return new self(
            uuid: $account->uuid,
            method: $account->method,
            currency: $account->currency,
            label: $account->label,
            beneficiary: $account->beneficiary,
            bankName: $account->bank_name,
            iban: $account->iban,
            bic: $account->bic,
            accountNumber: $account->account_number,
            routingNumber: $account->routing_number,
            holderEmail: $account->holder_email,
            holderPhone: $account->holder_phone,
            instructions: $account->instructions,
            isDefault: $account->is_default,
            isActive: $account->is_active,
            sortOrder: $account->sort_order,
            createdAt: $account->created_at?->toIso8601String(),
            updatedAt: $account->updated_at?->toIso8601String(),
            deletedAt: $account->deleted_at?->toIso8601String(),
        );
    }
}
