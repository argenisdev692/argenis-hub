<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;

/**
 * @extends Factory<InvoiceEloquentModel>
 */
final class InvoiceFactory extends Factory
{
    protected $model = InvoiceEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 9999);

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'client_id' => ClientEloquentModel::factory(),
            'product_id' => null,
            'invoice_number' => sprintf('%03d/%d', $sequence, 2026),
            'sequence' => $sequence,
            'year' => 2026,
            'issue_date' => '2026-03-10',
            'due_date' => '2026-04-10',
            'currency' => 'USD',
            'tax_mode' => 'EXEMPT',
            'tax_rate' => null,
            'tax_label' => 'IVA',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'is_paid' => false,
        ];
    }

    public function paid(PaymentMethod $method = PaymentMethod::Remitly): self
    {
        return $this->state(fn (): array => [
            'is_paid' => true,
            'payment_method' => $method,
            'payment_date' => '2026-03-12',
            'amount_received' => 35.00,
        ]);
    }
}
