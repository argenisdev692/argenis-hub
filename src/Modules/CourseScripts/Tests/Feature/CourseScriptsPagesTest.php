<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;

/*
 * The contract the Vue pages under `resources/js/pages/course-scripts/` rely on:
 * the Inertia components exist (`inertia.testing.ensure_pages_exist`), carry
 * the props they are typed against, and the JSON list serves the flat paginator
 * shape `useCourses()` reads, honouring every filter the toolbar sends.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->author = CourseScriptTestUsers::author();
});

describe('inertia pages', function (): void {
    it('renders the index page', function (): void {
        $this->actingAs($this->author)
            ->get(route('course-scripts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('course-scripts/Index'));
    });

    it('renders the create page with the upload limits', function (): void {
        $this->actingAs($this->author)
            ->get(route('course-scripts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('course-scripts/Create')
                ->has('limits', fn (Assert $limits): Assert => $limits
                    ->whereType('max_kb', 'integer')
                    ->whereType('max_content_files', 'integer')
                    ->whereType('allowed_extensions', 'array')));
    });

    it('renders the show page with the course detail', function (): void {
        $course = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);

        $this->actingAs($this->author)
            ->get(route('course-scripts.show', $course->uuid))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('course-scripts/Show')
                ->where('course.uuid', $course->uuid)
                ->has('course.videos')
                ->has('course.blocks')
                ->has('course.documents'));
    });
});

describe('json list consumed by the data table', function (): void {
    it('returns the flat paginator shape', function (): void {
        CourseEloquentModel::factory()->count(2)->create(['user_id' => $this->author->id]);

        $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['per_page' => 15]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('current_page', 1)
            ->assertJsonStructure([
                'data' => [['uuid', 'title', 'language', 'status', 'videos_count', 'generated_videos_count', 'created_at', 'updated_at', 'deleted_at']],
                'last_page', 'per_page', 'from', 'to',
            ]);
    });

    it('filters by the trashed axis', function (): void {
        CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
        CourseEloquentModel::factory()->create(['user_id' => $this->author->id])->delete();

        $list = fn (string $trashed): int => $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['trashed' => $trashed]))
            ->json('total');

        expect($list('without'))->toBe(1)
            ->and($list('only'))->toBe(1)
            ->and($list('with'))->toBe(2);
    });

    it('filters by status and search, and sorts by an allowed field', function (): void {
        CourseEloquentModel::factory()->create(['user_id' => $this->author->id, 'title' => 'Alpha course', 'status' => CourseStatus::Draft]);
        CourseEloquentModel::factory()->create(['user_id' => $this->author->id, 'title' => 'Beta course', 'status' => CourseStatus::Completed]);

        $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['status' => 'completed']))
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Beta course');

        $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['search' => 'Alpha']))
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Alpha course');

        $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['sort_field' => 'title', 'sort_order' => 1]))
            ->assertJsonPath('data.0.title', 'Alpha course');
    });

    it('rejects a sort field outside the allow-list', function (): void {
        $this->actingAs($this->author)
            ->getJson(route('course-scripts.index', ['sort_field' => 'user_id']))
            ->assertUnprocessable();
    });
});
