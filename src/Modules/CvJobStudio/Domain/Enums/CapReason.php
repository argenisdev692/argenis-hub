<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum CapReason: string
{
    case UnreadableRequirements = 'unreadable_requirements';
    case CredentialOrFloorOrLanguage = 'credential_or_floor_or_language';
    case WeakEvidence = 'weak_evidence';
}
