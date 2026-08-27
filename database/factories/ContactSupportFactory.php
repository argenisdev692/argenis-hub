<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * @extends Factory<ContactSupportEloquentModel>
 */
final class ContactSupportFactory extends Factory
{
    protected $model = ContactSupportEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            // Public submissions have no owner; `forUser()` sets one explicitly.
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1'.fake()->numerify('##########'),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'sms_consent' => fake()->boolean(30),
            'readed' => false,
            'is_spam' => false,
            'spam_score' => 0,
            'spam_reasons' => null,
        ];
    }

    public function read(): self
    {
        return $this->state(fn (): array => ['readed' => true]);
    }

    public function spam(): self
    {
        return $this->state(fn (): array => [
            'is_spam' => true,
            'spam_score' => fake()->numberBetween(70, 100),
            'spam_reasons' => ['honeypot', 'link_spam'],
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
