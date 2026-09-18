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
