<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Services\SkillRelationSet;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/**
 * Deterministic prompt layers (T-151): confirmed structure by ordinal, sorted
 * keys, no timestamps or ids — identical input yields byte-identical layers,
 * with `cacheKey = cvjs:{purpose}:{content_hash}`. A long-lived layer after a
 * short-lived one is rejected by `CacheablePrompt`.
 */
final readonly class CvSnapshotLayer
{
    /**
     * @param  array{entries: list<array{ordinal: int, organization: string|null, role_title: string|null}>, skills: list<array{canonical_name: string, evidence: string}>}  $structure
     */
    #[\NoDiscard]
    public static function snapshot(array $structure, string $contentHash): PromptLayer
    {
        $entries = $structure['entries'];
        usort($entries, static fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);

        $skills = array_map(
            static fn (array $skill): string => SkillRelationSet::normalize($skill['canonical_name']).':'.$skill['evidence'],
            $structure['skills'],
        );
        sort($skills);

        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = "#{$entry['ordinal']} ".($entry['role_title'] ?? '').' @ '.($entry['organization'] ?? '');
        }

        return PromptLayer::long("CV snapshot {$contentHash}:\n".implode("\n", $lines)."\nSkills: ".implode(', ', $skills));
    }

    /**
     * @param  array<string, mixed>  $vocabulary  sorted term list for the extraction rubric
     */
    #[\NoDiscard]
    public static function rubric(array $vocabulary, string $promptVersion): PromptLayer
    {
        ksort($vocabulary);

        return PromptLayer::long("Extraction rubric {$promptVersion}:\n".json_encode($vocabulary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    #[\NoDiscard]
    public static function cached(string $purpose, PromptLayer $layer, string $tail, string $contentHash): CacheablePrompt
    {
        return new CacheablePrompt(
            layers: [$layer],
            tail: $tail,
            cacheKey: "cvjs:{$purpose}:{$contentHash}",
        );
    }
}
