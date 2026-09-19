<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Ports\DraftWriterPort;
use Modules\LeadScout\Domain\Services\DraftPlanner;
use Modules\LeadScout\Domain\Services\PersonalDataScrubber;
use Modules\LeadScout\Domain\Services\ProofPointMatcher;
use Modules\LeadScout\Domain\ValueObjects\DraftContext;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

/**
 * Writes a draft's opening sentence (spec US-5, T062): the model sees the
 * company evidence and the public proof summary ONLY — never the CV, never
 * a person's name (FR-25) — and every capability it claims is checked
 * against the confirmed profile.
 */
final readonly class ComposeDraftOpenerHandler
{
    private const int EVIDENCE_SIGNALS = 5;

    public function __construct(
        private ProofPointMatcher $proofs,
        private PersonalDataScrubber $scrubber,
        private DraftWriterPort $writer,
        private DraftPlanner $planner,
        private Config $config,
    ) {}

    /**
     * @return array{opener: string, proof: array{title: string, summary: string, url: ?string}|null, provider: string, model: string, unconfirmed_claims: list<string>}
     */
    public function handle(DraftContext $context, string $language, string $variant, ?string $provider, ?string $model): array
    {
        $profile = $context->profile;

        $proof = $this->proofs->match(
            $profile === null ? [] : $profile->proofPoints,
            $this->planner->companyTechs($context->signals),
            $context->company->sectors[0] ?? null,
        );

        $evidence = implode("\n", array_map(
            static fn (Signal $signal): string => trim((string) $signal->valueText.' — '.($signal->evidenceExcerpt ?? '')),
            array_slice($context->signals, 0, self::EVIDENCE_SIGNALS),
        ));

        $names = array_map(static fn (Contact $contact): string => (string) $contact->fullName, $context->contacts);

        $prompt = "Language: {$language}. Variant: {$variant}.\n\n"
            ."--- COMPANY EVIDENCE ---\n".$this->scrubber->scrub($evidence, $names)."\n\n"
            .'--- PUBLIC PROOF SUMMARY (the only capability reference allowed) ---'."\n"
            .($proof === null ? '(no citable proof yet)' : $this->scrubber->scrub("{$proof['title']}: {$proof['summary']}"))."\n";

        $written = $this->writer->write($prompt, $provider, $model);

        return [
            'opener' => $written['opener'],
            'proof' => $proof === null ? null : [
                'title' => (string) $proof['title'],
                'summary' => (string) $proof['summary'],
                'url' => $proof['url'],
            ],
            'provider' => $written['provider'],
            'model' => $written['model'],
            'unconfirmed_claims' => array_values(array_unique(SkillTaxonomy::unconfirmedClaims(
                $written['opener'],
                $profile === null ? [] : $profile->confirmedSkills,
                (array) $this->config->get('lead-scout.skills.watch_list', []),
            ))),
        ];
    }
}
