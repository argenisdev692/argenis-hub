<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\CourseScripts\Domain\Enums\ArtifactGenre;
use Modules\CourseScripts\Domain\Enums\ContentBlockType;
use Modules\CourseScripts\Domain\Enums\SectionKind;
use Modules\CourseScripts\Domain\Enums\SegmentType;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Ports\ScriptWriterPort;
use Modules\CourseScripts\Domain\Services\DocumentNameFactory;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;
use Psr\Log\LoggerInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCachingAIClient;
use Throwable;

/**
 * {@see ScriptWriterPort} over the shared AI bridge, with cache-friendly prompts
 * ({@see PromptCachingAIClient}).
 *
 * Model output is untrusted (OWASP LLM05): unknown enum values fall back to a
 * safe default, strings are trimmed and capped, file names are sanitised and
 * mapped consistently everywhere they appear. The deterministic gates then
 * check the structure.
 */
final readonly class LaravelAiScriptWriterAdapter implements ScriptWriterPort
{
    private const int MAX_TEXT = 12_000;

    public function __construct(
        private PromptCachingAIClient $generator,
        private WritingContextRenderer $renderer,
        private DocumentNameFactory $names,
        private LoggerInterface $logger,
    ) {}

    public function outline(VideoWritingContext $context, string $provider, array $corrections = []): ScriptDraft
    {
        $response = $this->call(GenerateScriptOutlineAgent::class, $context, $provider, 'outline', $this->tail(
            'Write the OUTLINE of this video.',
            null,
            $corrections,
        ));

        $fileNames = [];

        foreach ((array) ($response['practice_artifacts'] ?? []) as $artifact) {
            $original = (string) ($artifact['file_name'] ?? '');
            $fileNames[$original] = $this->names->artifactFile($original);
        }

        $file = fn (string $name): string => $fileNames[$name] ?? $this->names->artifactFile($name);

        $sections = [];

        foreach ((array) ($response['sections'] ?? []) as $row) {
            $number = $this->text($row['number'] ?? '', 10);

            if ($number === '') {
                continue;
            }

            $demo = strtoupper($this->text($row['demo_label'] ?? '', 20));

            $sections[] = [
                'number' => $number,
                'parent_number' => ($parent = $this->text($row['parent_number'] ?? '', 10)) === '' ? null : $parent,
                'title' => $this->text($row['title'] ?? '', 255),
                'kind' => (SectionKind::tryFrom((string) ($row['kind'] ?? '')) ?? SectionKind::Concept)->value,
                'minutes' => max(0, (int) ($row['minutes'] ?? 0)),
                'purpose' => $this->text($row['purpose'] ?? '', 1000),
                'demo_label' => $demo === '' ? null : $demo,
                'demo_purpose' => $demo === '' ? null : $this->text($row['demo_purpose'] ?? '', 1000),
                'practice_files' => array_values(array_unique(array_map($file, $this->strings($row['practice_files'] ?? [], 200)))),
                'segments' => [],
            ];
        }

        $warranted = (bool) ($response['practice_warranted'] ?? false) || $context->forcePractice;

        return new ScriptDraft(
            recordingFormat: $this->text($response['recording_format'] ?? '', 200),
            learningObjectives: $this->strings($response['learning_objectives'] ?? [], 500),
            continuityNote: $this->text($response['continuity_note'] ?? '', 3000),
            usesTool: (bool) ($response['uses_tool'] ?? false) && $context->taughtTool() !== null,
            sections: $sections,
            coverageMap: array_values(array_map(fn (mixed $entry): array => [
                'item' => $this->text(is_array($entry) ? ($entry['item'] ?? '') : '', 500),
                'section_numbers' => $this->strings(is_array($entry) ? ($entry['section_numbers'] ?? []) : [], 10),
            ], (array) ($response['coverage_map'] ?? []))),
            taughtSummary: $this->text($response['taught_summary'] ?? '', 1000),
            practicePlan: [
                'warranted' => $warranted,
                'reason' => $this->text($response['practice_reason'] ?? '', 2000),
                'topic' => $this->text($response['practice_topic'] ?? '', 120),
                'files_summary' => $this->text($response['practice_files_summary'] ?? '', 2000),
                'setup_instruction' => $this->text($response['practice_setup_instruction'] ?? '', 2000),
                'instructor_note' => $this->text($response['practice_instructor_note'] ?? '', 4000),
                'artifacts' => ! $warranted ? [] : array_values(array_map(fn (mixed $artifact): array => [
                    'file_name' => $file((string) (is_array($artifact) ? ($artifact['file_name'] ?? '') : '')),
                    'title' => $this->text(is_array($artifact) ? ($artifact['title'] ?? '') : '', 300),
                    'genre' => (ArtifactGenre::tryFrom((string) (is_array($artifact) ? ($artifact['genre'] ?? '') : '')) ?? ArtifactGenre::Other)->value,
                    'purpose' => $this->text(is_array($artifact) ? ($artifact['purpose'] ?? '') : '', 1000),
                    'used_by_demos' => array_map(strtoupper(...), $this->strings(is_array($artifact) ? ($artifact['used_by_demos'] ?? []) : [], 20)),
                    'organisations' => $this->strings(is_array($artifact) ? ($artifact['organisations'] ?? []) : [], 160),
                ], (array) ($response['practice_artifacts'] ?? []))),
                'contrasts' => ! $warranted ? [] : array_values(array_map(fn (mixed $contrast): array => [
                    'dimension' => $this->text(is_array($contrast) ? ($contrast['dimension'] ?? '') : '', 200),
                    'intended_effect' => $this->text(is_array($contrast) ? ($contrast['intended_effect'] ?? '') : '', 500),
                    'values' => array_values(array_map(fn (mixed $value): array => [
                        'file_name' => $file((string) (is_array($value) ? ($value['file_name'] ?? '') : '')),
                        'value' => $this->text(is_array($value) ? ($value['value'] ?? '') : '', 300),
                    ], is_array($contrast) ? (array) ($contrast['values'] ?? []) : [])),
                ], (array) ($response['practice_contrasts'] ?? []))),
            ],
        );
    }

    public function sectionSegments(VideoWritingContext $context, ScriptDraft $draft, string $sectionNumber, string $provider, array $corrections = []): array
    {
        $response = $this->call(GenerateScriptSectionAgent::class, $context, $provider, 'section', $this->tail(
            "Write the content of section {$sectionNumber} and its sub-sections.",
            UntrustedContentBlock::wrap('approved_outline', $this->outlineJson($draft)),
            $corrections,
        ));

        $allowedFiles = $draft->plannedFileNames();
        $parts = [];

        foreach ((array) ($response['parts'] ?? []) as $part) {
            $number = $this->text(is_array($part) ? ($part['section_number'] ?? '') : '', 10);

            if ($number === '') {
                continue;
            }

            $parts[$number] = array_values(array_map(function (mixed $segment) use ($allowedFiles): array {
                $segment = is_array($segment) ? $segment : [];
                $file = $this->names->artifactFile((string) ($segment['practice_file'] ?? ''));
                $rawFile = trim((string) ($segment['practice_file'] ?? ''));

                return [
                    'type' => (SegmentType::tryFrom((string) ($segment['type'] ?? '')) ?? SegmentType::Narration)->value,
                    'text' => $this->text($segment['text'] ?? '', self::MAX_TEXT),
                    'prompt' => $this->text($segment['prompt'] ?? '', self::MAX_TEXT),
                    'items' => $this->strings($segment['items'] ?? [], 1000),
                    'table_columns' => $this->strings($segment['table_columns'] ?? [], 200),
                    'table_rows' => $this->rows($segment['table_rows'] ?? []),
                    'read_aloud' => (bool) ($segment['read_aloud'] ?? false),
                    'practice_file' => $rawFile === '' ? null : (in_array($file, $allowedFiles, true) ? $file : $rawFile),
                ];
            }, (array) ($part['segments'] ?? [])));
        }

        return $parts;
    }

    public function closing(VideoWritingContext $context, ScriptDraft $draft, string $provider, array $corrections = []): array
    {
        $response = $this->call(GenerateScriptClosingAgent::class, $context, $provider, 'closing', $this->tail(
            'Write the CLOSING PARTS of this video.',
            UntrustedContentBlock::wrap('written_script', $this->outlineJson($draft, withSegments: true)),
            $corrections,
        ));

        return [
            'summary_points' => $this->strings($response['summary_points'] ?? [], 500),
            'next_video_handoff' => $this->text($response['next_video_handoff'] ?? '', 1000),
            'recording_notes' => [
                'preparation' => $this->strings($response['preparation'] ?? [], 1000),
                'during_recording' => $this->strings($response['during_recording'] ?? [], 1000),
                'tools_required' => $this->strings($response['tools_required'] ?? [], 200),
                'tools_none_reason' => $this->text($response['tools_none_reason'] ?? '', 1000),
                'continuity' => $this->text($response['continuity'] ?? '', 2000),
                'organisations_used' => $this->strings($response['organisations_used'] ?? [], 160),
            ],
            'verification_checklist' => $this->strings($response['verification_checklist'] ?? [], 500),
        ];
    }

    public function artifact(VideoWritingContext $context, ScriptDraft $draft, string $fileName, string $provider, array $corrections = []): array
    {
        $response = $this->call(GeneratePracticeArtifactAgent::class, $context, $provider, 'artifact', $this->tail(
            "Write the complete content of the practice file \"{$fileName}\".",
            UntrustedContentBlock::wrap('practice_plan', (string) json_encode([
                'file_to_write' => $fileName,
                'plan' => $draft->practicePlan,
                'demo_sections' => array_values(array_filter($draft->sections, static fn (array $section): bool => in_array($fileName, (array) $section['practice_files'], true))),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $corrections,
        ));

        return [
            'file_name' => $fileName,
            'content_blocks' => array_values(array_map(fn (mixed $block): array => [
                'type' => (ContentBlockType::tryFrom((string) (is_array($block) ? ($block['type'] ?? '') : '')) ?? ContentBlockType::Paragraph)->value,
                'level' => max(0, min(3, (int) (is_array($block) ? ($block['level'] ?? 0) : 0))),
                'text' => $this->text(is_array($block) ? ($block['text'] ?? '') : '', self::MAX_TEXT),
                'items' => $this->strings(is_array($block) ? ($block['items'] ?? []) : [], 1000),
                'table_header' => $this->strings(is_array($block) ? ($block['table_header'] ?? []) : [], 200),
                'table_rows' => $this->rows(is_array($block) ? ($block['table_rows'] ?? []) : []),
                'total_row' => (bool) (is_array($block) ? ($block['total_row'] ?? false) : false),
                'pairs' => array_values(array_map(fn (mixed $pair): array => [
                    'key' => $this->text(is_array($pair) ? ($pair['key'] ?? '') : '', 200),
                    'value' => $this->text(is_array($pair) ? ($pair['value'] ?? '') : '', 1000),
                ], is_array($block) ? (array) ($block['pairs'] ?? []) : [])),
            ], (array) ($response['content_blocks'] ?? []))),
            'organisations' => array_values(array_map(fn (mixed $row): array => [
                'name' => $this->text(is_array($row) ? ($row['name'] ?? '') : '', 160),
                'role' => $this->text(is_array($row) ? ($row['role'] ?? '') : '', 200),
                'sector' => $this->text(is_array($row) ? ($row['sector'] ?? '') : '', 120),
            ], (array) ($response['organisations'] ?? []))),
            'characters' => array_values(array_map(fn (mixed $row): array => [
                'name' => $this->text(is_array($row) ? ($row['name'] ?? '') : '', 120),
                'role' => $this->text(is_array($row) ? ($row['role'] ?? '') : '', 200),
                'organisation' => $this->text(is_array($row) ? ($row['organisation'] ?? '') : '', 160),
            ], (array) ($response['characters'] ?? []))),
        ];
    }

    /**
     * @param  class-string  $agent
     */
    private function call(string $agent, VideoWritingContext $context, string $provider, string $step, string $tail): StructuredAgentResponse
    {
        try {
            return $this->generator->generateStructured($agent, $this->renderer->prompt($context, $tail), $provider);
        } catch (Throwable $exception) {
            // Class name only: provider errors can echo the author's content (FR-55).
            $this->logger->error('course_scripts.writer_failed', ['step' => $step, 'exception' => $exception::class]);

            throw GenerationProviderException::providerFailed($step);
        }
    }

    /**
     * @param  list<string>  $corrections
     */
    private function tail(string $request, ?string $material, array $corrections): string
    {
        $parts = [];

        if ($material !== null) {
            $parts[] = $material;
        }

        $parts[] = 'REQUEST: '.$request;

        if ($corrections !== []) {
            $parts[] = "CORRECTIONS TO FIX FROM THE PREVIOUS ATTEMPT:\n- ".implode("\n- ", $corrections);
        }

        return implode("\n\n", $parts);
    }

    private function outlineJson(ScriptDraft $draft, bool $withSegments = false): string
    {
        return (string) json_encode([
            'recording_format' => $draft->recordingFormat,
            'learning_objectives' => $draft->learningObjectives,
            'continuity_note' => $draft->continuityNote,
            'uses_tool' => $draft->usesTool,
            'sections' => array_map(
                static fn (array $section): array => $withSegments ? $section : array_diff_key($section, ['segments' => true]),
                $draft->sections,
            ),
            'practice_plan' => $draft->practicePlan,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function text(mixed $value, int $max): string
    {
        return mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $max);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values, int $max): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $value): string => $this->text($value, $max), is_array($values) ? $values : []),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @return list<list<string>>
     */
    private function rows(mixed $rows): array
    {
        return array_values(array_map(
            fn (mixed $row): array => array_values(array_map(fn (mixed $cell): string => $this->text($cell, 500), is_array($row) ? $row : [])),
            is_array($rows) ? array_slice($rows, 0, 200) : [],
        ));
    }
}
