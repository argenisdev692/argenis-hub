<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum GateCode: string
{
    case RemoteScope = 'G1';
    case StackLock = 'G2';
    case ListingQuality = 'G3';
    case SnippetRequirements = 'G4';
    case SnippetSeniority = 'G4b';
}
