<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * Kinds of simulated document a practice pack can contain (FR-36a).
 */
enum ArtifactGenre: string
{
    case Proposal = 'proposal';
    case Report = 'report';
    case Email = 'email';
    case EmailThread = 'email_thread';
    case MeetingNotes = 'meeting_notes';
    case Dataset = 'dataset';
    case Policy = 'policy';
    case Contract = 'contract';
    case ChatTranscript = 'chat_transcript';
    case ContextBrief = 'context_brief';
    case ComparisonCase = 'comparison_case';
    case Other = 'other';
}
