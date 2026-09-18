<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/**
 * Confirmed seed rows from the JOBORA alias table (T-110, CHG-12, Q16):
 * JavaScript ⇄ JS/ES6/ECMAScript, TypeScript ⇄ TS, Vue.js ⇄ Vue/VueJS/Vue 3,
 * Node.js ⇄ Node/NodeJS, PostgreSQL ⇄ Postgres, REST APIs ⇄ REST/RESTful,
 * family CI/CD ⇄ GitHub Actions.
 */
class StudioSkillRelationSeeder extends Seeder
{
    /** @var list<array{from: string, to: string, kind: string}> */
    private const array RELATIONS = [
        ['from' => 'JavaScript', 'to' => 'JS', 'kind' => 'alias'],
        ['from' => 'JavaScript', 'to' => 'ES6', 'kind' => 'alias'],
        ['from' => 'JavaScript', 'to' => 'ECMAScript', 'kind' => 'alias'],
        ['from' => 'TypeScript', 'to' => 'TS', 'kind' => 'alias'],
        ['from' => 'Vue.js', 'to' => 'Vue', 'kind' => 'alias'],
        ['from' => 'Vue.js', 'to' => 'VueJS', 'kind' => 'alias'],
        ['from' => 'Vue.js', 'to' => 'Vue 3', 'kind' => 'alias'],
        ['from' => 'Node.js', 'to' => 'Node', 'kind' => 'alias'],
        ['from' => 'Node.js', 'to' => 'NodeJS', 'kind' => 'alias'],
        ['from' => 'PostgreSQL', 'to' => 'Postgres', 'kind' => 'alias'],
        ['from' => 'REST APIs', 'to' => 'REST', 'kind' => 'alias'],
        ['from' => 'REST APIs', 'to' => 'RESTful', 'kind' => 'alias'],
        ['from' => 'CI/CD', 'to' => 'GitHub Actions', 'kind' => 'family'],
    ];

    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->command->warn('StudioSkillRelationSeeder: no user found, skipping.');

            return;
        }

        foreach (self::RELATIONS as $relation) {
            StudioSkillRelationEloquentModel::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'from_skill' => $relation['from'],
                    'to_skill' => $relation['to'],
                    'kind' => $relation['kind'],
                ],
                [
                    'uuid' => (string) Str::uuid7(),
                    'origin' => 'seed',
                    'status' => 'confirmed',
                ],
            );
        }
    }
}
