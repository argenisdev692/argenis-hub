<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * `GET /data/admin/contact-supports/export` — streams the filtered inbox as
 * CSV / Excel / PDF through the Shared `ExportPort`. Reuses the SAME
 * `ContactSupportEloquentModel::applyFilters()` as the admin list, so these
 * tests only assert what export adds: the guard, the format switch, and that
 * the active filters reach the stream.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function contactSupportExportOperator(array $permissions = ['EXPORT_CONTACT_SUPPORTS']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away', function (): void {
        $this->getJson(route('contact-supports.admin.export'))->assertUnauthorized();
    });

    it('refuses a signed-in user without EXPORT_CONTACT_SUPPORTS', function (): void {
        $this->actingAs(contactSupportExportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->get(route('contact-supports.admin.export'))
            ->assertForbidden();
    });
});

describe('formats', function (): void {
    it('defaults to an Excel download', function (): void {
        ContactSupportEloquentModel::factory()->create();

        $response = $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export'));

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    });

    it('streams a CSV with a header row and the inbox rows', function (): void {
        ContactSupportEloquentModel::factory()->create([
            'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com',
        ]);

        $content = $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Name', 'Email', 'Phone', 'Subject', 'Read', 'SMS Consent', 'Spam', 'Created', 'Status')
            ->toContain('Grace Hopper', 'grace@example.com');
    });

    it('renders a PDF', function (): void {
        ContactSupportEloquentModel::factory()->create();

        $response = $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export', ['format' => 'pdf']))
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export', ['format' => 'json']))
            ->assertStatus(422);
    });
});

describe('respects the active filters', function (): void {
    it('applies the search term to the exported rows', function (): void {
        ContactSupportEloquentModel::factory()->create(['email' => 'keep@example.com']);
        ContactSupportEloquentModel::factory()->create(['email' => 'drop@example.com']);

        $content = $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export', ['format' => 'csv', 'search' => 'keep@example']))
            ->streamedContent();

        expect($content)
            ->toContain('keep@example.com')
            ->not->toContain('drop@example.com');
    });

    it('applies the status filter to the exported rows', function (): void {
        ContactSupportEloquentModel::factory()->create(['email' => 'kept@example.com']);
        ContactSupportEloquentModel::factory()->create(['email' => 'gone@example.com'])->delete();

        $content = $this->actingAs(contactSupportExportOperator())
            ->get(route('contact-supports.admin.export', ['format' => 'csv', 'status' => 'deleted']))
            ->streamedContent();

        expect($content)
            ->toContain('gone@example.com', 'Suspended')
            ->not->toContain('kept@example.com');
    });

    it('rejects an inverted date range', function (): void {
        $this->actingAs(contactSupportExportOperator())
            ->getJson(route('contact-supports.admin.export', [
                'format' => 'csv',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-10',
            ]))
            ->assertStatus(422);
    });
});
