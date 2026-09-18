<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum RemoteScope: string
{
    case RemoteGlobal = 'remote_global';
    case RemoteEu = 'remote_eu';
    case RemotePtEs = 'remote_pt_es';
    case RemoteUnclear = 'remote_unclear';
    case HybridLocal = 'hybrid_local';
}
