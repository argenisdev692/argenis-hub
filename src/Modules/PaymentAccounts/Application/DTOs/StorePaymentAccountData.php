<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\DTOs;

use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Shared\Domain\Enums\Currency;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Fused create/update DTO (DTO Fusion Rule — store and update share 100% of
 * fields).
 *
 * `currency = null` means the account settles any currency. A BANK_TRANSFER
 * account must carry either an IBAN or an account number, otherwise the PDF has
 * nothing to print in the "how to pay" block.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class StorePaymentAccountData extends Data
{
    public function __construct(
        public PaymentMethod $method,
        public string $label,
        public ?string $currency = null,
        public ?string $beneficiary = null,
        public ?string $bankName = null,
        public ?string $iban = null,
        public ?string $bic = null,
        public ?string $accountNumber = null,
        public ?string $routingNumber = null,
        public ?string $holderEmail = null,
        public ?string $holderPhone = null,
        public ?string $instructions = null,
        public bool $isDefault = false,
        public bool $isActive = true,
        public int $sortOrder = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'method' => ['required', 'string', 'in:'.implode(',', PaymentMethod::values())],
            'label' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'in:'.implode(',', Currency::values())],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => [
                'nullable',
                'required_without_all:account_number,holder_email,holder_phone',
                'string',
                'max:64',
                'regex:/^[A-Z]{2}[0-9A-Z \\-]{8,60}$/i',
            ],
            'bic' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]{8,11}$/'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'routing_number' => ['nullable', 'string', 'max:64'],
            'holder_email' => ['nullable', 'email', 'max:255'],
            'holder_phone' => ['nullable', 'string', 'max:32'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
