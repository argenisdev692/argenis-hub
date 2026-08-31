<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Human review/edit pass over an AI-generated package (content is always
 * AI-born in v1 — there is no manual "create" form, only generate → review →
 * publish). Per-platform copy is edited by re-running generation, not by hand
 * here; this DTO covers the master fields + lifecycle the review screen
 * actually exposes.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateSocialMediaContentData extends Data
{
    /**
     * @param  list<string>  $hashtags
     */
    public function __construct(
        public string $headline,
        public string $body,
        public string $callToAction,
        public array $hashtags,
        public string $status,
        public ?string $scheduledAt = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'call_to_action' => ['required', 'string', 'max:500'],
            'hashtags' => ['array', 'max:20'],
            'hashtags.*' => ['string', 'max:50'],
            'status' => ['required', 'string', Rule::in([
                'draft', 'ready', 'needs_review', 'published', 'scheduled',
            ])],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:status,scheduled'],
        ];
    }
}
