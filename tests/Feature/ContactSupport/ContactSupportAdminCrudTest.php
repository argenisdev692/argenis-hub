<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * `/data/admin/contact-supports` — the JSON CRUD surface the support-inbox
 * table consumes. Every route is guarded by its own `*_CONTACT_SUPPORTS`
 * permission (seeded by `RolePermissionSeeder`).
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function contactSupportOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactSupportPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+14155552671',
        'subject' => 'Website enquiry',
        'message' => 'I would like a quote for a landing page.',
        'sms_consent' => true,
        ...$overrides,
    ];
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->getJson(route('contact-supports.admin.index'))->assertUnauthorized();
        $this->getJson(route('contact-supports.admin.show', $support->uuid))->assertUnauthorized();
        $this->postJson(route('contact-supports.admin.store'), contactSupportPayload())->assertUnauthorized();
        $this->putJson(route('contact-supports.admin.update', $support->uuid), ['readed' => true])->assertUnauthorized();
        $this->deleteJson(route('contact-supports.admin.destroy', $support->uuid))->assertUnauthorized();
        $this->patchJson(route('contact-supports.admin.restore', $support->uuid))->assertUnauthorized();
        $this->postJson(route('contact-supports.admin.bulk-delete'), ['uuids' => [$support->uuid]])->assertUnauthorized();
        $this->postJson(route('contact-supports.admin.bulk-restore'), ['uuids' => [$support->uuid]])->assertUnauthorized();
    });

    it('refuses a signed-in user who holds no contact-support permission', function (): void {
        $this->actingAs(contactSupportOperator([]))
            ->getJson(route('contact-supports.admin.index'))
            ->assertForbidden();
    });

    it('refuses a reader on the write routes', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS', 'VIEW_CONTACT_SUPPORTS']))
            ->putJson(route('contact-supports.admin.update', $support->uuid), ['readed' => true])
            ->assertForbidden();
    });
});

describe('listing', function (): void {
    it('paginates active and soft-deleted requests together by default', function (): void {
        ContactSupportEloquentModel::factory()->count(2)->create();
        ContactSupportEloquentModel::factory()->create()->delete();

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('serializes the paginator flat, not nested under a "meta" key', function (): void {
        ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'from', 'to', 'total'])
            ->assertJsonMissingPath('meta');
    });

    it('filters by search term across name, email and subject', function (): void {
        ContactSupportEloquentModel::factory()->create(['email' => 'keep@example.com', 'subject' => 'Quote please']);
        ContactSupportEloquentModel::factory()->create(['email' => 'other@example.com', 'subject' => 'Unrelated']);

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index', ['search' => 'keep@example']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'keep@example.com');
    });

    it('filters by the readed toggle', function (): void {
        ContactSupportEloquentModel::factory()->read()->create();
        ContactSupportEloquentModel::factory()->create(['readed' => false]);

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index', ['readed' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.readed', false);
    });

    it('filters by the spam toggle', function (): void {
        ContactSupportEloquentModel::factory()->spam()->create();
        ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index', ['is_spam' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_spam', true);
    });

    it('never exposes the auto-increment key or the owner column', function (): void {
        ContactSupportEloquentModel::factory()->create();

        $response = $this->actingAs(contactSupportOperator(['VIEW_ANY_CONTACT_SUPPORTS']))
            ->getJson(route('contact-supports.admin.index'))
            ->assertOk();

        expect($response->json('data.0'))
            ->not->toHaveKey('id')
            ->not->toHaveKey('user_id');
    });
});

describe('creating', function (): void {
    it('creates a request attributed to the acting operator', function (): void {
        $operator = contactSupportOperator(['CREATE_CONTACT_SUPPORTS']);

        $this->actingAs($operator)
            ->postJson(route('contact-supports.admin.store'), contactSupportPayload())
            ->assertCreated()
            ->assertJsonPath('subject', 'Website enquiry')
            ->assertJsonPath('readed', false);

        $support = ContactSupportEloquentModel::query()->where('email', 'ada@example.com')->firstOrFail();
        expect($support->user_id)->toBe($operator->id)
            ->and($support->is_spam)->toBeFalse();
    });

    it('rejects invalid input', function (array $overrides, string $field): void {
        $this->actingAs(contactSupportOperator(['CREATE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.store'), contactSupportPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing first name' => [['first_name' => ''], 'first_name'],
        'missing email' => [['email' => ''], 'email'],
        'malformed email' => [['email' => 'not-an-email'], 'email'],
        'malformed phone' => [['phone' => 'call-me'], 'phone'],
        'subject too long' => [['subject' => str_repeat('a', 151)], 'subject'],
        'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    ]);
});

describe('updating', function (): void {
    it('flips the readed flag without resending the record', function (): void {
        $support = ContactSupportEloquentModel::factory()->create(['readed' => false]);

        $this->actingAs(contactSupportOperator(['UPDATE_CONTACT_SUPPORTS']))
            ->putJson(route('contact-supports.admin.update', $support->uuid), ['readed' => true])
            ->assertOk()
            ->assertJsonPath('readed', true);

        expect($support->refresh()->readed)->toBeTrue();
    });

    it('marks a request as spam', function (): void {
        $support = ContactSupportEloquentModel::factory()->create(['is_spam' => false]);

        $this->actingAs(contactSupportOperator(['UPDATE_CONTACT_SUPPORTS']))
            ->putJson(route('contact-supports.admin.update', $support->uuid), ['is_spam' => true])
            ->assertOk()
            ->assertJsonPath('is_spam', true);
    });

    it('rejects an empty update body', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['UPDATE_CONTACT_SUPPORTS']))
            ->putJson(route('contact-supports.admin.update', $support->uuid), [])
            ->assertUnprocessable();
    });

    it('records the change in the audit trail', function (): void {
        $support = ContactSupportEloquentModel::factory()->create(['readed' => false]);

        $this->actingAs(contactSupportOperator(['UPDATE_CONTACT_SUPPORTS']))
            ->putJson(route('contact-supports.admin.update', $support->uuid), ['readed' => true])
            ->assertOk();

        $activity = $support->activitiesAsSubject()->where('event', 'updated')->latest('id')->first();
        $changes = $activity?->attribute_changes?->toArray() ?? [];

        expect($activity?->log_name)->toBe('contact-support.contact-support')
            ->and($changes['attributes']['readed'] ?? null)->toBeTrue();
    });
});

describe('deleting and restoring', function (): void {
    it('soft-deletes a request', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['DELETE_CONTACT_SUPPORTS']))
            ->deleteJson(route('contact-supports.admin.destroy', $support->uuid))
            ->assertNoContent();

        expect(ContactSupportEloquentModel::query()->whereKey($support->getKey())->exists())->toBeFalse()
            ->and(ContactSupportEloquentModel::withTrashed()->whereKey($support->getKey())->exists())->toBeTrue();
    });

    it('restores a soft-deleted request', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();
        $support->delete();

        $this->actingAs(contactSupportOperator(['RESTORE_CONTACT_SUPPORTS']))
            ->patchJson(route('contact-supports.admin.restore', $support->uuid))
            ->assertOk()
            ->assertJsonPath('deleted_at', null);

        expect($support->fresh())->not->toBeNull();
    });
});

describe('bulk operations', function (): void {
    it('bulk soft-deletes the selected requests', function (): void {
        $rows = ContactSupportEloquentModel::factory()->count(3)->create();

        $this->actingAs(contactSupportOperator(['BULK_DELETE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.bulk-delete'), ['uuids' => $rows->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        expect(ContactSupportEloquentModel::query()->count())->toBe(0)
            ->and(ContactSupportEloquentModel::withTrashed()->count())->toBe(3);
    });

    it('bulk restores the selected requests', function (): void {
        $rows = ContactSupportEloquentModel::factory()->count(2)->create();
        ContactSupportEloquentModel::query()->whereIn('uuid', $rows->pluck('uuid'))->delete();

        $this->actingAs(contactSupportOperator(['BULK_RESTORE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.bulk-restore'), ['uuids' => $rows->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('restored', 2);

        expect(ContactSupportEloquentModel::query()->count())->toBe(2);
    });

    it('refuses bulk delete without the bulk permission', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['DELETE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.bulk-delete'), ['uuids' => [$support->uuid]])
            ->assertForbidden();
    });

    it('rejects an empty uuid list', function (): void {
        $this->actingAs(contactSupportOperator(['BULK_DELETE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.bulk-delete'), ['uuids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids');
    });

    it('rejects a malformed uuid in the list', function (): void {
        $support = ContactSupportEloquentModel::factory()->create();

        $this->actingAs(contactSupportOperator(['BULK_DELETE_CONTACT_SUPPORTS']))
            ->postJson(route('contact-supports.admin.bulk-delete'), ['uuids' => [$support->uuid, 'not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids.1');
    });
});
