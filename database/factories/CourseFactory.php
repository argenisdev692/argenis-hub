<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * @extends Factory<CourseEloquentModel>
 */
final class CourseFactory extends Factory
{
    protected $model = CourseEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'language' => 'es',
            'declared_duration_minutes' => null,
            'default_video_minutes' => 8,
            'status' => CourseStatus::Ready,
            'course_notes' => null,
            'bible' => null,
            'bible_origin' => null,
            'bible_revision' => 0,
        ];
    }

    public function withBible(): self
    {
        return $this->state(fn (): array => [
            'bible' => [
                'organisations' => [
                    ['key' => 'tecnoform', 'name' => 'Tecnoform S.A.', 'role' => 'Empresa del alumno', 'sector' => 'Industria', 'is_primary' => true],
                ],
                'characters' => [
                    ['name' => 'Marta', 'role' => 'Directora de operaciones', 'organisation_key' => 'tecnoform'],
                ],
                'audience' => 'Profesionales no técnicos',
                'tone' => 'Cercano y práctico',
                'taught_tool' => 'Claude',
                'forbidden_phrasings' => [],
            ],
            'bible_origin' => BibleOrigin::Author,
            'bible_revision' => 1,
            'prepared_at' => now(),
        ]);
    }

    /**
     * A course with one block and `$count` 9-minute videos in course order.
     */
    public function withVideos(int $count = 3): self
    {
        return $this->afterCreating(function (CourseEloquentModel $course) use ($count): void {
            $block = CourseBlockEloquentModel::query()->create([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Bloque 1',
                'declared_duration_minutes' => $count * 9,
                'position' => 0,
            ]);

            foreach (range(1, $count) as $number) {
                CourseVideoEloquentModel::query()->create([
                    'course_id' => $course->id,
                    'course_block_id' => $block->id,
                    'number' => $number,
                    'title' => 'Vídeo '.$number,
                    'declared_duration_minutes' => 9,
                    'learning_areas' => [],
                    'audience_objectives' => [],
                    'mandatory_content' => ['Contenido obligatorio '.$number],
                    'errors_to_avoid' => [],
                    'script_status' => VideoScriptStatus::NotStarted,
                ]);
            }
        });
    }
}
