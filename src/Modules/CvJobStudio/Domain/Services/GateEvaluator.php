<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\Enums\GateCode;
use Modules\CvJobStudio\Domain\Enums\RemoteScope;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\ValueObjects\GateVerdict;

/**
 * Hard gates G1/G2/G3 (FR-12). Gate-failed postings are counted and reasoned
 * but never scored. All inputs are plain arrays so the service stays pure.
 */
final readonly class GateEvaluator
{
    /**
     * The level at which a declared skill counts as an anchor. Below it the
     * skill is real but not the thing the candidate leads with, so a job whose
     * core is only that skill is not filtered out.
     */
    private const int FLOOR_ANCHOR_LEVEL = 4;

    /**
     * @param  array{remote_scope: string, title: string, text: string, url: string}  $posting
     * @param  array{accepted_remote_scopes: list<string>, stack_must: list<string>, stack_reject: list<string>}  $profile
     * @return list<GateVerdict>
     */
    #[\NoDiscard]
    public function evaluate(array $posting, array $profile): array
    {
        $this->guardRequiredInputs($profile);

        return [
            $this->gateRemoteScope($posting, $profile),
            $this->gateStackLock($posting, $profile),
            $this->gateListingQuality($posting),
            $this->gateSkillFloor($posting, $profile),
        ];
    }

    /**
     * @param  array{accepted_remote_scopes: list<string>, stack_must: list<string>, stack_reject: list<string>}  $profile
     */
    private function guardRequiredInputs(array $profile): void
    {
        $missing = [];

        if (($profile['accepted_remote_scopes'] ?? []) === []) {
            $missing[] = 'accepted_remote_scopes';
        }

        if ($missing !== []) {
            throw new ProfileGateIncompleteException($missing);
        }
    }

    /**
     * @param  array{remote_scope: string}  $posting
     * @param  array{accepted_remote_scopes: list<string>}  $profile
     */
    private function gateRemoteScope(array $posting, array $profile): GateVerdict
    {
        $scope = RemoteScope::tryFrom($posting['remote_scope'] ?? '') ?? RemoteScope::RemoteUnclear;
        $passed = in_array($scope->value, $profile['accepted_remote_scopes'], true);

        return new GateVerdict(
            gate: GateCode::RemoteScope,
            passed: $passed,
            reasonCode: $passed ? null : 'scope_not_accepted',
            detail: $passed ? null : "Scope {$scope->value} is not in the profile's accepted list.",
        );
    }

    /**
     * @param  array{title: string, text: string}  $posting
     * @param  array{stack_must: list<string>, stack_reject: list<string>}  $profile
     */
    private function gateStackLock(array $posting, array $profile): GateVerdict
    {
        $haystack = mb_strtolower($posting['title'].' '.$posting['text']);

        foreach ($profile['stack_reject'] ?? [] as $rejected) {
            if ($rejected !== '' && str_contains($haystack, mb_strtolower($rejected))) {
                return new GateVerdict(GateCode::StackLock, false, 'stack_rejected', "Rejected stack term '{$rejected}' is present.");
            }
        }

        $must = array_filter($profile['stack_must'] ?? [], static fn (string $term): bool => $term !== '');

        if ($must !== []) {
            foreach ($must as $required) {
                if (str_contains($haystack, mb_strtolower($required))) {
                    return new GateVerdict(GateCode::StackLock, true);
                }
            }

            return new GateVerdict(GateCode::StackLock, false, 'stack_missing', 'None of the required stack terms is present.');
        }

        return new GateVerdict(GateCode::StackLock, true);
    }

    /**
     * Per-skill defensibility as a shortlist FILTER (never a score input).
     *
     * The candidate is not uniformly strong: four years of Laravel with a
     * frontend on its second or third year is a real shape, and a job whose
     * core requirement is the weak half is worth skipping even though it would
     * still land mid-band. The operator declares `{skill: level}` with
     * 0 absent / 1 junior / 2 mid / 3 solid / 4 anchor, and this gate fails the
     * posting only when NONE of its required requirements names a skill at or
     * above that declared level.
     *
     * Two deliberate non-behaviours:
     *
     *  - `skill_floors` empty or absent ⇒ always pass. A missing preference
     *    must never start blocking postings; that is the safe direction.
     *  - No requirements, or none that is `required`/`hard` ⇒ always pass. The
     *    filter expresses a preference about a job's core, and a posting that
     *    states no core cannot contradict it.
     *
     * `creditFor()` is untouched on purpose: it feeds the score, and the score
     * is calibrated (SC-2 recomputes from stored data, SC-3 holds the band
     * against a hand-judged set). A filter can only remove a candidate from
     * consideration, which is a reversible kind of decision.
     *
     * @param  array{title: string, text: string}  $posting
     * @param  array{skill_floors?: array<string, int>, stack_must?: list<string>}  $profile
     */
    /**
     * The skill-floor gate on its own, for the stage that owns the requirements.
     *
     * G5 is the one gate whose input is not the posting text: it needs the
     * extracted requirements, and extraction runs AFTER the gate stage
     * (Gate → Shortlist → Extract). `evaluate()` therefore always passes G5
     * pre-extraction, and this is the call that produces the authoritative
     * verdict, overwriting that provisional pass.
     *
     * Exposed separately on purpose: re-running `evaluate()` here would rewrite
     * G1/G2/G3 from inputs this stage does not have, replacing authoritative
     * results with guesses.
     *
     * @param  array<string, mixed>  $posting
     * @param  array<string, mixed>  $profile
     */
    public function skillFloorVerdict(array $posting, array $profile): GateVerdict
    {
        return $this->gateSkillFloor($posting, $profile);
    }

    /**
     * @param  array{title: string, text: string}  $posting
     * @param  array{skill_floors?: array<string, int>, stack_must?: list<string>}  $profile
     */
    private function gateSkillFloor(array $posting, array $profile): GateVerdict
    {
        $floors = $profile['skill_floors'] ?? [];

        if ($floors === []) {
            return new GateVerdict(GateCode::SkillFloor, true);
        }

        $anchors = [];

        foreach ($floors as $skill => $level) {
            $skill = trim((string) $skill);
            $level = (int) $level;

            if ($skill !== '' && $level >= self::FLOOR_ANCHOR_LEVEL) {
                $anchors[] = mb_strtolower($skill);
            }
        }

        if ($anchors === []) {
            return new GateVerdict(GateCode::SkillFloor, true);
        }

        // No extracted requirements yet (the gate also runs at ingest, before
        // extraction): nothing to contradict, so the posting is let through.
        $required = $this->requiredSkillTerms($posting);

        if ($required === []) {
            return new GateVerdict(GateCode::SkillFloor, true);
        }

        foreach ($anchors as $anchor) {
            foreach ($required as $term) {
                if ($this->matches($anchor, $term)) {
                    return new GateVerdict(GateCode::SkillFloor, true);
                }
            }
        }

        return new GateVerdict(
            GateCode::SkillFloor,
            false,
            'anchor_missing',
            'No required requirement matches an anchor skill of the profile.',
        );
    }

    /**
     * Required/hard requirement names the posting declares, in the shapes the
     * pipeline stores them: a `requirements` array of rows, or a flattened
     * `required_requirements` list. A name longer than two words is skipped —
     * it is a sentence, not a skill, and substring matching on prose is how a
     * gate starts passing the wrong postings.
     *
     * @param  array<string, mixed>  $posting
     * @return list<string>
     */
    private function requiredSkillTerms(array $posting): array
    {
        $rows = $posting['requirements'] ?? [];

        if (! is_array($rows) || $rows === []) {
            $rows = array_map(
                static fn (mixed $name): array => ['canonical_name' => $name],
                (array) ($posting['required_requirements'] ?? []),
            );
        }

        $terms = [];

        foreach ($rows as $row) {
            $name = is_array($row)
                ? (string) ($row['canonical_name'] ?? '')
                : (string) $row;
            $tag = is_array($row) ? (string) ($row['tag'] ?? 'required') : 'required';

            $name = trim($name);

            if ($name === '' || $tag === 'preferred' || $tag === 'bonus') {
                continue;
            }

            if (mb_strlen($name) > 40 || str_contains($name, ' ')) {
                continue;
            }

            $terms[] = mb_strtolower($name);
        }

        return array_values(array_unique($terms));
    }

    /**
     * Whole-token containment, so the anchor `React` never "matches" `Reactive`
     * and `C#` never matches every `.cs` string. Aliases (React.js) collapse
     * because the dot is a separator, not a word.
     */
    private function matches(string $anchor, string $term): bool
    {
        if ($anchor === $term) {
            return true;
        }

        $pattern = '/(?<![a-z0-9])'.preg_quote($anchor, '/').'(?![a-z0-9])/i';

        return preg_match($pattern, $term) === 1;
    }

    /**
     * @param  array{title: string, text: string, url: string}  $posting
     */
    private function gateListingQuality(array $posting): GateVerdict
    {
        if (trim($posting['title'] ?? '') === '' || trim($posting['text'] ?? '') === '') {
            return new GateVerdict(GateCode::ListingQuality, false, 'empty_content', 'Title or text is empty.');
        }

        $url = mb_strtolower($posting['url'] ?? '');

        if (str_contains($url, '/search') || str_contains($url, 'q=') || str_contains($url, '/jobs?')) {
            return new GateVerdict(GateCode::ListingQuality, false, 'aggregator_page', 'URL looks like a search-results page, not a single posting.');
        }

        return new GateVerdict(GateCode::ListingQuality, true);
    }
}
