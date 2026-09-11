<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\Exceptions\InsufficientWorkspaceException;

/**
 * Ephemeral local scratch space for one edit (AD-12). Everything inside is
 * wiped when processing ends, successfully or not (FR-18).
 */
interface VideoEditWorkspacePort
{
    /**
     * Creates an empty directory for the edit and returns its absolute path.
     *
     * @throws InsufficientWorkspaceException
     */
    public function prepare(string $videoEditUuid, int $requiredBytes): string;

    /**
     * Absolute path of a file inside the edit's directory. Only the base name of
     * `$fileName` is used, so callers can never escape the workspace.
     */
    public function path(string $videoEditUuid, string $fileName): string;

    public function wipe(string $videoEditUuid): void;
}
