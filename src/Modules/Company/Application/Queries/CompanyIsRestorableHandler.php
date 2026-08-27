<?php

declare(strict_types=1);

namespace Modules\Company\Application\Queries;

use Modules\Company\Domain\Ports\CompanyRepositoryPort;

/**
 * Whether the company settings screen should offer a "restore" action.
 *
 * True only when there is no active record but a soft-deleted one exists. Lets
 * the controller's `show()` choose between the "deleted, restore it" screen and
 * a plain 404 without reaching for Eloquent.
 */
final readonly class CompanyIsRestorableHandler
{
    public function __construct(private CompanyRepositoryPort $companies) {}

    #[\NoDiscard('handle() returns whether a soft-deleted company record exists.')]
    public function handle(): bool
    {
        return $this->companies->trashedExists();
    }
}
