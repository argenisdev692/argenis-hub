<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

/**
 * @extends Factory<PaymentAccountEloquentModel>
 */
final class PaymentAccountFactory extends Factory
{
    protected $model = PaymentAccountEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'method' => PaymentMethod::BankTransfer,
            'currency' => 'EUR',
            'label' => 'Montepio EUR',
            'beneficiary' => 'Argenis Jose Carrillo Gonzalez',
            'bank_name' => 'Montepio',
            'iban' => 'PT50003600119910006305349',
            'bic' => 'MPIOPTPL',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * The USD side of the same issuer: same person, different rail.
     */
    public function remitlyUsd(): self
    {
        return $this->state(fn (): array => [
            'method' => PaymentMethod::Remitly,
            'currency' => 'USD',
            'label' => 'Remitly USD',
            'bank_name' => null,
            'iban' => null,
            'bic' => null,
            'holder_email' => 'argenis692@gmail.com',
            'holder_phone' => '+351963490414',
        ]);
    }

    /**
     * USD settled by wire — proof that method and currency are orthogonal.
     */
    public function bankTransferUsd(): self
    {
        return $this->state(fn (): array => [
            'method' => PaymentMethod::BankTransfer,
            'currency' => 'USD',
            'label' => 'USD wire',
            'iban' => null,
            'bic' => null,
            'account_number' => '1234567890',
            'routing_number' => '021000021',
        ]);
    }

    public function notDefault(): self
    {
        return $this->state(fn (): array => ['is_default' => false]);
    }
}
