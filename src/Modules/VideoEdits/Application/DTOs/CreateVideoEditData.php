<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Closure;
use Illuminate\Validation\Rule;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Create-draft request (E2 · US-1/2/3/7). Validates structure only; everything
 * that depends on the real media happens after upload (AD-2, AD-16).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CreateVideoEditData extends Data
{
    /**
     * @param  list<SourceUploadData>  $sources
     * @param  list<ManualRangeData>  $manualRanges
     */
    public function __construct(
        public VideoEditMode $mode,
        #[DataCollectionOf(SourceUploadData::class)]
        public array $sources,
        public ?SilenceRemovalData $silenceRemoval = null,
        public ?SpeechCleanupData $speechCleanup = null,
        public ?AiEditData $aiEdit = null,
        #[DataCollectionOf(ManualRangeData::class)]
        public array $manualRanges = [],
        public ?string $previousEditUuid = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $payload = $context?->fullPayload ?? [];
        $mode = VideoEditMode::tryFrom((string) ($payload['mode'] ?? ''));
        $isMerge = $mode === VideoEditMode::Merge;
        $isAiEdit = $mode === VideoEditMode::AiEdit;
        $limits = (array) config('video-edit.limits');
        $silence = (array) config('video-edit.silence');
        $script = (array) config('video-edit.ai.script');
        $extensions = implode('|', array_map(preg_quote(...), (array) $limits['allowed_extensions']));

        return [
            'mode' => ['required', 'string', Rule::in(VideoEditMode::availableValues()), self::requiresSomethingToCut($payload)],
            'sources' => ['required', 'array', 'min:'.($mode?->minimumSources() ?? 1), 'max:'.(int) $limits['max_sources']],
            'sources.*.position' => ['required', 'integer', 'min:1', 'max:'.(int) $limits['max_sources'], 'distinct'],
            'sources.*.file_name' => ['required', 'string', 'max:255', "regex:/\\.({$extensions})$/i"],
            'sources.*.mime_type' => ['required', 'string', Rule::in((array) $limits['allowed_mime_types'])],
            'sources.*.size_bytes' => ['required', 'integer', 'min:1', 'max:'.(int) $limits['max_file_bytes']],
            'silence_removal' => [Rule::prohibitedIf($isMerge), 'nullable', 'array'],
            'silence_removal.enabled' => ['required_with:silence_removal', 'boolean'],
            'silence_removal.threshold_seconds' => [
                'nullable',
                'numeric',
                'min:'.$silence['min_threshold_seconds'],
                'max:'.$silence['max_threshold_seconds'],
            ],
            // V3 — AI edit. Only valid for the ai_edit mode, and only with
            // explicit consent to send the transcript and script out (R9).
            'ai_edit' => [Rule::requiredIf($isAiEdit), Rule::prohibitedIf(! $isAiEdit), 'nullable', 'array'],
            'ai_edit.enabled' => ['required_with:ai_edit', 'boolean'],
            // `accepted` alone would also reject a MISSING value, which would
            // fail every non-AI request; it only applies once the mode asks for it.
            'ai_edit.consented' => $isAiEdit ? ['required', 'accepted'] : ['nullable', 'boolean'],
            'ai_edit.instructions' => ['nullable', 'string', 'max:20000'],
            'ai_edit.target_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'ai_edit.script' => ['nullable', 'array'],
            'ai_edit.script.file_name' => [
                'required_with:ai_edit.script', 'string', 'max:255',
                'regex:/\\.('.implode('|', array_map(preg_quote(...), (array) $script['allowed_extensions'])).')$/i',
            ],
            'ai_edit.script.mime_type' => ['required_with:ai_edit.script', 'string', Rule::in((array) $script['allowed_mime_types'])],
            'ai_edit.script.size_bytes' => ['required_with:ai_edit.script', 'integer', 'min:1', 'max:'.(int) $script['max_file_bytes']],
            // V2 — one boolean turns Whisper on; categories narrow it (US-10).
            // Required for an AI edit: V3 reasons over the V2 transcript, so
            // without speech cleanup there would be nothing for it to read and
            // the edit would quietly do nothing.
            'speech_cleanup' => [Rule::prohibitedIf($isMerge), Rule::requiredIf($isAiEdit), 'nullable', 'array'],
            'speech_cleanup.enabled' => ['required_with:speech_cleanup', 'boolean', ...($isAiEdit ? ['accepted'] : [])],
            'speech_cleanup.categories' => ['nullable', 'array'],
            'speech_cleanup.categories.*' => ['string', Rule::in(SpeechCategory::values())],
            'speech_cleanup.language' => ['nullable', 'string', 'size:2'],
            ...ManualRangeData::listRules('manual_ranges'),
            'manual_ranges' => [
                Rule::prohibitedIf($isMerge),
                'array',
                'max:'.(int) $limits['max_manual_ranges'],
            ],
            'previous_edit_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'mode.in' => 'This editing mode is not available yet.',
            'ai_edit.consented.accepted' => 'AI edit needs your explicit consent to send the transcript and script to the AI provider.',
            'speech_cleanup.enabled.accepted' => 'AI edit reads the transcript produced by speech cleanup, so it has to be enabled.',
            'ai_edit.script.file_name.regex' => 'A script must be a .md or .pdf file.',
            'sources.*.file_name.regex' => 'Only MP4, MOV, WebM and MKV videos can be edited.',
            'manual_ranges.*.end_ms.gt' => 'Each range must end after it starts.',
        ];
    }

    public function silenceRemovalEnabled(): bool
    {
        return $this->silenceRemoval?->enabled === true;
    }

    /**
     * Auto edit with nothing to cut would just re-encode the video.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function requiresSomethingToCut(array $payload): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($payload): void {
            if ($value !== VideoEditMode::AutoEdit->value) {
                return;
            }

            $silenceEnabled = filter_var($payload['silence_removal']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $speechEnabled = filter_var($payload['speech_cleanup']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $hasRanges = is_array($payload['manual_ranges'] ?? null) && $payload['manual_ranges'] !== [];

            if (! $silenceEnabled && ! $speechEnabled && ! $hasRanges) {
                $fail('Enable silence removal or speech cleanup, or add at least one range to cut.');
            }
        };
    }
}
