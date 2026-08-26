<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Exceptions;

use RuntimeException;

/**
 * Raised when the singleton company row does not exist yet.
 *
 * The row is created by `CompanySeeder`, never by the HTTP surface — this module
 * edits the company, it does not create companies. A missing row therefore means
 * the installation was never seeded, which is a 404 for every caller (there is
 * nothing to show and nothing to edit), not a 500.
 *
 * Mapped to an HTTP 404 in `bootstrap/app.php` so the Domain stays free of HTTP.
 */
final class CompanyNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self('The company profile has not been configured yet.');
    }
}
