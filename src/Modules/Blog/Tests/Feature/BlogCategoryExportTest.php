<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function blogCategoryExporter(array $permissions = ['EXPORT_BLOG_CATEGORIES']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function blogCategoryStreamedBody(Response $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('streams the blog-category list as CSV with the mandated column set', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Exportable']);

    $response = $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=csv')
        ->assertOk();

    $body = blogCategoryStreamedBody($response->baseResponse);

    expect($body)->toContain('Name', 'Description', 'Author', 'Created', 'Status')
        ->and($body)->toContain('Exportable');
});

it('streams an xlsx export with the spreadsheet content type', function (): void {
    BlogCategoryEloquentModel::factory()->create();

    $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=xlsx')
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
});

it('renders a pdf export', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Printable']);

    $response = $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=pdf')
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('labels soft-deleted rows Suspended, never Inactive', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Retired'])->delete();

    $response = $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=csv&status=suspended')
        ->assertOk();

    $body = blogCategoryStreamedBody($response->baseResponse);

    expect($body)->toContain('Retired', 'Suspended')
        ->and($body)->not->toContain('Inactive');
});

it('applies the same search filter as the list query', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Kept']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Dropped']);

    $response = $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=csv&search=Kept')
        ->assertOk();

    $body = blogCategoryStreamedBody($response->baseResponse);

    expect($body)->toContain('Kept')
        ->and($body)->not->toContain('Dropped');
});

it('rejects an unsupported export format', function (): void {
    $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export?format=exe')
        ->assertStatus(422);
});

it('rejects an export date range whose end precedes its start', function (): void {
    $this->actingAs(blogCategoryExporter())
        ->getJson('/blog-categories/export?format=csv&date_from=2026-05-10&date_to=2026-05-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_from');
});

it('forbids exporting without EXPORT_BLOG_CATEGORIES', function (): void {
    $this->actingAs(blogCategoryExporter(['VIEW_ANY_BLOG_CATEGORIES']))
        ->get('/blog-categories/export?format=csv')
        ->assertForbidden();
});

it('does not let the export route swallow the bulk-delete segment', function (): void {
    $this->actingAs(blogCategoryExporter())
        ->get('/blog-categories/export')
        ->assertOk();
});
