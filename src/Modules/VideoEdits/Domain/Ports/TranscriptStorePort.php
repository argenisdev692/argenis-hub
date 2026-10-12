<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Application\Transcription\TranscriptionIdentity;
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
     * in this order, on exactly this provider and model, or null when there is
     * none.
     *
     * `$provider` and `$model` are part of the lookup, not decoration on the row.
     * Word timings are model-specific — a different segmenter places the same
     * word milliseconds apart — and every cut in this module is addressed by word
     * index and resolved against these timestamps, so a transcript from another
     * model is not a cheaper copy of this one but a wrong one. Without them in
     * the key, switching `video-edit.provider` hands back the previous model's
     * timings and every cut lands somewhere the speaker did not say.
     *
     * @see TranscriptionIdentity
     */
    public function findReusable(
        int $userId,
        string $sourceFingerprint,
        string $provider,
        string $model,
    ): ?Transcript;

    public function store(
        int $videoEditId,
        string $sourceFingerprint,
        Transcript $transcript,
        string $provider,
        string $model,
    ): void;
}
