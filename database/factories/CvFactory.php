<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Enums\CvNiche;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * @extends Factory<CvEloquentModel>
 */
final class CvFactory extends Factory
{
    protected $model = CvEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst((string) fake()->unique()->words(3, true));
        $type = fake()->randomElement(CvFileType::cases());

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'title' => $title,
            'niche' => fake()->randomElement(CvNiche::cases()),
            'is_primary' => false,
            'file_path' => 'cvs/'.Str::uuid7().'.'.$type->value,
            'file_type' => $type,
            'original_filename' => Str::slug($title).'.'.$type->value,
            'raw_text' => fake()->paragraphs(3, true),
        ];
    }

    public function primary(): self
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }

    public function markdown(): self
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => CvFileType::Md,
            'file_path' => 'cvs/'.Str::uuid7().'.md',
            'original_filename' => Str::slug((string) ($attributes['title'] ?? 'resume')).'.md',
        ]);
    }

    public function pdf(): self
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => CvFileType::Pdf,
            'file_path' => 'cvs/'.Str::uuid7().'.pdf',
            'original_filename' => Str::slug((string) ($attributes['title'] ?? 'resume')).'.pdf',
            'raw_text' => null,
        ]);
    }
}
