<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Transcription;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Modules\VideoEdits\Providers\VideoEditsServiceProvider;

/**
 * Which transcriber this installation is configured to use, as the two strings
 * the transcript store records alongside every transcript.
 *
 * **Why this exists as its own object.** Provider and model are a single fact —
 * "what produced this transcript" — and four call sites need it: the one that
 * stores a transcript and the three that try to reuse one. Reading
 * `video-edit.provider` in each of them is how {@see
 * Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer}
 * ended up labelling every transcript `openai`/`whisper-1` no matter which
 * transcriber actually produced it, which made the reuse key below useless: a
 * gemini transcript was filed under whisper-1 and a whisper-1 transcript was
 * handed back to a gemini run.
 *
 * **A transcript is only reusable by the thing that would have produced it.**
 * Word timings are not portable between models — a different segmenter puts the
 * same word milliseconds apart — so a transcript from another provider is not a
 * cheaper copy of this one, it is a wrong one. Cuts in this module are addressed
 * by word index and resolved against these timestamps, so reusing across
 * providers silently moves every cut.
 */
final readonly class TranscriptionIdentity
{
    /**
     * The providers this module can bind. Kept beside the model lookup so the
     * two cannot drift apart: a provider added to
     * {@see VideoEditsServiceProvider::transcriber()}
     * without a model key here would store an empty model and never match on
     * reuse.
     *
     * @var array<string, string> provider to the config key holding its model
     */
    private const array MODEL_KEYS = [
        'openai' => 'video-edit.speech.openai.model',
        'gemini' => 'video-edit.speech.gemini.model',
    ];

    public function __construct(private ConfigRepository $config) {}

    /**
     * @throws InvalidArgumentException when the configured provider is unknown,
     *                                  which is a deployment error rather than
     *                                  anything the pipeline can recover from.
     */
    public function provider(): string
    {
        $provider = (string) $this->config->get('video-edit.provider', 'openai');

        if (! array_key_exists($provider, self::MODEL_KEYS)) {
            throw new InvalidArgumentException(
                "Unknown video-edit transcription provider [{$provider}]. Use "
                .implode(' or ', array_keys(self::MODEL_KEYS)).'.',
            );
        }

        return $provider;
    }

    public function model(): string
    {
        return (string) $this->config->get(self::MODEL_KEYS[$this->provider()], '');
    }
}
