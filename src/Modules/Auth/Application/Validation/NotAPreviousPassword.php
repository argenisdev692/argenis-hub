<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Auth\Domain\Ports\PasswordHistoryPort;

/**
 * Rejects a password the user has recently used (FR-12).
 *
 * Resolved from the container so the port is injected rather than located, then
 * bound to a subject with {@see self::for()}. Without a subject the rule passes:
 * registration has no history to compare against, and failing closed there would
 * block every new account.
 */
final readonly class NotAPreviousPassword implements ValidationRule
{
    public function __construct(
        private PasswordHistoryPort $history,
        private ?string $userUuid = null,
    ) {}

    /**
     * Bind the rule to the user whose history must be checked.
     */
    #[\NoDiscard('The rule is immutable — use the returned instance.')]
    public function for(?string $userUuid): self
    {
        return clone ($this, ['userUuid' => $userUuid]);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->userUuid === null || ! is_string($value) || $value === '') {
            return;
        }

        if ($this->history->matchesRecent($this->userUuid, $value)) {
            $fail(__('This password was used recently. Please choose a different one.'));
        }
    }
}
