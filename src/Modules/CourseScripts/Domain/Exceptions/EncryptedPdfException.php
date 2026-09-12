<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * The PDF is password-protected or otherwise secured.
 *
 * Given its own type rather than folding into
 * {@see UnrecognisableIndexException} because the author's remedy is entirely
 * different — remove the protection and re-upload, not fix the content.
 * Upstream states secured documents are unsupported (research R1.4).
 */
final class EncryptedPdfException extends RuntimeException
{
    public const string CODE = 'encrypted_pdf';

    public function __construct()
    {
        parent::__construct('This PDF is protected. Remove its password and upload it again.');
    }
}
