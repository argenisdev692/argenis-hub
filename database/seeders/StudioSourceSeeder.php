<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioChannelBaselineEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSourceEloquentModel;

/**
 * Source registry from `portal-tiers-cache.json` v4 (T-010) with an
 * `access_mode` per domain from `research.md` §17.5 — unchecked terms default
 * to `link_only` (FR-52). Idempotent and re-runnable. Channel baselines seed
 * the Q19 option-(b) mild-directional policy (T-111, V-1).
 */
class StudioSourceSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const array SOURCES = [
        ['name' => 'greenhouse', 'kind' => 'ats_board', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 10, 'supplies_full_text' => true, 'company_scoped' => true, 'ats_kind' => 'greenhouse', 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'lever', 'kind' => 'ats_board', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 11, 'supplies_full_text' => true, 'company_scoped' => true, 'ats_kind' => 'lever', 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'ashby', 'kind' => 'ats_board', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 12, 'supplies_full_text' => true, 'company_scoped' => true, 'ats_kind' => 'ashby', 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'arbeitnow', 'kind' => 'board_api', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 20, 'supplies_full_text' => true, 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'itjobs', 'kind' => 'board_api', 'tier' => 2, 'layer' => 'core', 'resolution_priority' => 21, 'requires_api_key' => true, 'access_mode' => 'api_feed', 'resolution_tier' => 1, 'usage_restriction' => 'personal_non_commercial'],
        ['name' => 'net-empregos', 'kind' => 'rss', 'tier' => 2, 'layer' => 'core', 'resolution_priority' => 22, 'access_mode' => 'api_feed', 'resolution_tier' => 1, 'usage_restriction' => 'personal_non_commercial'],
        ['name' => 'remoteok', 'kind' => 'board_api', 'tier' => 2, 'layer' => 'expansion', 'resolution_priority' => 30, 'attribution_required' => true, 'attribution_text' => 'RemoteOK', 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'remotive', 'kind' => 'board_api', 'tier' => 2, 'layer' => 'expansion', 'resolution_priority' => 31, 'attribution_required' => true, 'attribution_text' => 'Remotive', 'publish_delay_hours' => 24, 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'recruitee', 'kind' => 'ats_board', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 13, 'supplies_full_text' => true, 'company_scoped' => true, 'ats_kind' => 'recruitee', 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'workable', 'kind' => 'ats_board', 'tier' => 1, 'layer' => 'core', 'resolution_priority' => 14, 'supplies_full_text' => true, 'company_scoped' => true, 'ats_kind' => 'workable', 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'teamtailor', 'kind' => 'ats_board', 'tier' => 2, 'layer' => 'expansion', 'resolution_priority' => 23, 'company_scoped' => true, 'ats_kind' => 'teamtailor', 'requires_api_key' => false, 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'smartrecruiters', 'kind' => 'ats_board', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 95, 'company_scoped' => true, 'ats_kind' => 'smartrecruiters', 'access_mode' => 'link_only', 'resolution_tier' => 3],
        ['name' => 'wwr', 'kind' => 'rss', 'tier' => 2, 'layer' => 'core', 'resolution_priority' => 24, 'access_mode' => 'api_feed', 'resolution_tier' => 1],
        ['name' => 'remoteok', 'kind' => 'board_api', 'tier' => 2, 'layer' => 'expansion', 'resolution_priority' => 32, 'attribution_required' => true, 'attribution_text' => 'RemoteOK', 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'remotive', 'kind' => 'board_api', 'tier' => 2, 'layer' => 'expansion', 'resolution_priority' => 33, 'attribution_required' => true, 'attribution_text' => 'Remotive', 'publish_delay_hours' => 24, 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'himalayas', 'kind' => 'board_api', 'tier' => 3, 'layer' => 'expansion', 'resolution_priority' => 40, 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'jobicy', 'kind' => 'board_api', 'tier' => 3, 'layer' => 'expansion', 'resolution_priority' => 41, 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'adzuna', 'kind' => 'board_api', 'tier' => 3, 'layer' => 'expansion', 'resolution_priority' => 42, 'requires_api_key' => true, 'access_mode' => 'api_feed', 'resolution_tier' => 2],
        ['name' => 'tavily', 'kind' => 'search_api', 'tier' => 3, 'layer' => 'core', 'resolution_priority' => 50, 'access_mode' => 'search_scoped', 'resolution_tier' => 3],
        ['name' => 'linkedin', 'kind' => 'search_scoped', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 90, 'access_mode' => 'link_only', 'resolution_tier' => 3],
        ['name' => 'indeed', 'kind' => 'search_scoped', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 91, 'access_mode' => 'link_only', 'resolution_tier' => 3],
        ['name' => 'tecnoempleo', 'kind' => 'search_scoped', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 92, 'access_mode' => 'link_only', 'resolution_tier' => 3],
        ['name' => 'glassdoor', 'kind' => 'search_scoped', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 93, 'access_mode' => 'link_only', 'resolution_tier' => 3],
        ['name' => 'infojobs', 'kind' => 'search_scoped', 'tier' => 4, 'layer' => 'expansion', 'resolution_priority' => 94, 'access_mode' => 'link_only', 'resolution_tier' => 3, 'usage_restriction' => 'partner_agreement_required'],
        ['name' => 'remoterocketship', 'kind' => 'board_api', 'tier' => 3, 'layer' => 'expansion', 'resolution_priority' => 60, 'access_mode' => 'resolve_only', 'resolution_tier' => 2],
    ];

    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->command->warn('StudioSourceSeeder: no user found, skipping.');

            return;
        }

        foreach (self::SOURCES as $source) {
            StudioSourceEloquentModel::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $source['name']],
                [...$source, 'user_id' => $user->id, 'uuid' => (string) Str::uuid7(), 'status' => 'active'],
            );
        }

        foreach ((array) config('cv-job-studio.opportunity.channel', []) as $channel => $entry) {
            StudioChannelBaselineEloquentModel::query()->firstOrCreate(
                ['user_id' => $user->id, 'bucket_kind' => 'channel', 'bucket' => $channel],
                [
                    'uuid' => (string) Str::uuid7(),
                    'policy_value' => $entry['value'],
                    'grade' => $entry['grade'],
                    'source' => $entry['source'],
                ],
            );
        }
    }
}
