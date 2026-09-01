<?php

declare(strict_types=1);

namespace Modules\Campaigns\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Campaigns\Domain\Enums\CampaignStatus;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Tests\TestCase;

final class PublishScheduledCampaignsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_campaigns_whose_scheduled_at_has_passed(): void
    {
        $due = CampaignEloquentModel::factory()->create([
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        $future = CampaignEloquentModel::factory()->create([
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        Artisan::call('campaigns:publish-scheduled');

        $due->refresh();
        $future->refresh();

        $this->assertSame('published', $due->status->value);
        $this->assertNotNull($due->published_at);
        $this->assertSame('scheduled', $future->status->value);
        $this->assertNull($future->published_at);
    }

    public function test_it_reports_zero_when_nothing_is_due(): void
    {
        CampaignEloquentModel::factory()->create([
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        $exitCode = Artisan::call('campaigns:publish-scheduled');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Published 0 scheduled campaign(s).', Artisan::output());
    }
}
