<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * Persistence for transcripts, so re-editing identical sources does not pay for
 * transcription twice (US-11).
 *
 * Reuse is scoped to the owner on purpose: a transcript is the user's speech
 * verbatim, so matching fingerprints across accounts would leak one user's
 * words into another's edit.
 */
interface TranscriptStorePort
{
    /**
     * The most recent transcript this user produced for exactly these sources,
     * in this order, or null when there is none.
     */
    public function findReusable(int $userId, string $sourceFingerprint): ?Transcript;

    public function store(
        int $videoEditId,
        string $sourceFingerprint,
        Transcript $transcript,
        string $provider,
        string $model,
    ): void;
}
