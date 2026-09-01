<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain\Exceptions;

use Modules\Authorization\Domain\SystemRoles;
use RuntimeException;

/**
 * Raised when a mutation would rename or delete a protected system role
 * (see {@see SystemRoles}). The module's controllers catch it and redirect back
 * with a flashed `error`, so the UI surfaces a validation-style message rather
 * than a 500 — the Domain layer never imports Laravel HTTP.
 */
final class ProtectedRoleException extends RuntimeException
{
    public static function cannotModify(string $roleName): self
    {
        return new self("The system role \"{$roleName}\" is protected and cannot be renamed or deleted.");
    }
}
