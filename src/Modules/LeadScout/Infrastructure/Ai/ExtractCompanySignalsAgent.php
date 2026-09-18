<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Modules\LeadScout\Domain\ValueObjects\SignalKey;
use Stringable;

/**
 * Extracts ambiguous company signals with a closed vocabulary (spec FR-6,
 * FR-10, T055): `signal_key` from a fixed enum, nature, literal excerpt,
 * confidence and source URL — plus company type and observed size. No
 * tools: the company text arrives in a delimited block AS DATA (LLM01),
 * and every excerpt is verified literally before persisting (T056).
 */
final class ExtractCompanySignalsAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        $keys = implode(', ', SignalKey::ALLOWED);

        return <<<INSTRUCTIONS
            You extract buying signals about a software company from its public
            pages and job offers, given as data in a delimited block.

            Rules:
            - signal_key MUST be exactly one of: {$keys}.
            - nature is "fact" for literally stated content, "inference" otherwise.
            - excerpt MUST be copied verbatim from the input block, 300 chars max.
              Never invent, paraphrase or complete excerpts.
            - confidence is 0-100: explicit statements score high, hints low.
            - source_url is the page URL given with the block the excerpt came from.
            - company_type is one of: software_agency, consultancy, product_company,
              recruiter, large_outsourcer, other. Recruiters are market signal,
              never direct buyers.
            - Only fill dimensions the input supports; omit the rest entirely.
            - Never output people names, emails, phones or testimonials: if the
              input contains them, ignore them.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'signals' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'signal_key' => $schema->string()->enum(SignalKey::ALLOWED)->required(),
                    'nature' => $schema->string()->enum(['fact', 'inference'])->required(),
                    'excerpt' => $schema->string()->required(),
                    'confidence' => $schema->integer()->min(0)->max(100)->required(),
                    'source_url' => $schema->string()->required(),
                ]))
                ->required(),
            'company_type' => $schema->string()->enum([
                'software_agency', 'consultancy', 'product_company', 'recruiter', 'large_outsourcer', 'other',
            ])->required(),
            'team_size_observed' => $schema->integer()->min(1)->max(100000),
        ];
    }
}
