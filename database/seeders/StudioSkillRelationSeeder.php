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
 *
 * React and Inertia.js rows added 2026-09-25 with the React pivot. Without
 * them a posting asking for "React.js" or "ReactJS" scored an UNCOVERED
 * requirement against a CV that ships React in production: `SkillRelationSet`
 * credits a match by exact name or by a confirmed relation, so a missing alias
 * is a false negative in the insight report and a lost credit in the score.
 * `stack_must` already listed `Inertia.js`, but a posting saying plain
 * `Inertia` did not resolve to it for the same reason.
 */
class StudioSkillRelationSeeder extends Seeder
{
    /** @var list<array{from: string, to: string, kind: string}> */
    private const array RELATIONS = [
        ['from' => 'JavaScript', 'to' => 'JS', 'kind' => 'alias'],
        ['from' => 'JavaScript', 'to' => 'ES6', 'kind' => 'alias'],
        ['from' => 'JavaScript', 'to' => 'ECMAScript', 'kind' => 'alias'],
        ['from' => 'TypeScript', 'to' => 'TS', 'kind' => 'alias'],
        ['from' => 'React', 'to' => 'React.js', 'kind' => 'alias'],
        ['from' => 'React', 'to' => 'ReactJS', 'kind' => 'alias'],
        ['from' => 'React', 'to' => 'React 19', 'kind' => 'alias'],
        ['from' => 'Inertia.js', 'to' => 'Inertia', 'kind' => 'alias'],
        ['from' => 'Inertia.js', 'to' => 'Inertia v3', 'kind' => 'alias'],
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
