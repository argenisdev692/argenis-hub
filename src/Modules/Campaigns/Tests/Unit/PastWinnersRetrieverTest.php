<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Campaigns\Infrastructure\Ai\PastWinnersRetriever;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

beforeEach(function (): void {
    $this->author = User::factory()->create();
    $this->other = User::factory()->create();

    $this->winner = function (User $user, string $topic, int $avg, ?string $niche = 'Lead generation'): void {
        CampaignEloquentModel::factory()->create([
            'created_by' => $user->id,
            'topic' => $topic,
            'niche' => $niche,
            'hook' => "Hook for {$topic}",
            'all_scores_pass' => true,
            'overall_score_avg' => $avg,
        ]);
    };
});

it('returns the authors own passing campaigns best first', function (): void {
    ($this->winner)($this->author, 'Winner A', 95);
    ($this->winner)($this->author, 'Winner B', 82);
    ($this->winner)($this->author, 'Flop', 40, 'Gardening');

    CampaignEloquentModel::factory()->create([
        'created_by' => $this->author->id,
        'topic' => 'Unjudged draft',
        'all_scores_pass' => false,
        'overall_score_avg' => 99,
    ]);

    $winners = app(PastWinnersRetriever::class)->forBrief($this->author->id, 'Lead generation', 'Lead generation');

    expect(array_column($winners, 'topic'))->toBe(['Winner A', 'Winner B'])
        ->and($winners[0]['hook'])->toBe('Hook for Winner A');
});

it('never leaks another authors winners and stays silent without a user', function (): void {
    ($this->winner)($this->other, 'Other Winner', 99);

    $retriever = app(PastWinnersRetriever::class);

    expect($retriever->forBrief($this->author->id, 'Lead generation', 'Lead generation'))->toBe([])
        ->and($retriever->forBrief(null, 'Lead generation', 'Lead generation'))->toBe([])
        ->and($retriever->toPromptBlock([]))->toBe('');
});

it('formats winners as one compact prompt block', function (): void {
    $block = app(PastWinnersRetriever::class)->toPromptBlock([
        ['topic' => 'Winner A', 'hook' => 'Hook A', 'virality_score' => 85, 'roi_potential_score' => 74],
    ]);

    expect($block)->toContain('Winner A')
        ->and($block)->toContain('Hook A')
        ->and($block)->toContain('roi: 74');
});
