<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

/**
 * LLM purposes (T-150, CHG-20). Each purpose owns its provider, fallbacks,
 * model per provider, timeout, input/output caps, cache flag and prompt
 * version in `ai.purposes` config. Cache is ON only where measurement proves
 * reuse pays (SC-18).
 */
enum AiPurpose: string
{
    case RequirementExtraction = 'requirement_extraction';
    case MatchNarrative = 'match_narrative';
    case Tailor = 'tailor';
    case Translate = 'translate';
    case CvRewrite = 'cv_rewrite';
    case CvJudge = 'cv_judge';
    case CvStructureParse = 'cv_structure_parse';
}
