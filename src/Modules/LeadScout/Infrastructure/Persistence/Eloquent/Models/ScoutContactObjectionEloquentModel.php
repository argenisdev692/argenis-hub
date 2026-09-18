<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Person-level objection fingerprint (spec FR-25, FR-29): the hash — never
 * cleartext — of an opposed person, so re-extraction skips them.
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_contact_objections')]
#[Fillable([
    'person_hash',
])]
final class ScoutContactObjectionEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];
}
