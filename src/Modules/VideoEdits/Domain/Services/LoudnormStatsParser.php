<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\LoudnessMeasurement;

/**
 * Parses the JSON `loudnorm` prints during a measurement pass.
 *
 * FFmpeg writes the block to stderr, mixed with its normal stream dump, so the
 * parser looks for the last object carrying `input_i` rather than assuming the
 * whole stderr is JSON. Values arrive as strings (`"-25.22"`, `"-inf"`), which
 * is why they are validated before being turned into floats.
 *
 * A null answer is a normal outcome, not an error: silence has no integrated
 * loudness, and a filtergraph must then run without a loudness stage rather than
 * with an invented measurement.
 */
final readonly class LoudnormStatsParser
{
    /**
     * The five fields the second pass needs. Kept in one place so the parser and
     * the graph builder cannot drift apart.
     *
     * @var list<string>
     */
    private const array REQUIRED = ['input_i', 'input_tp', 'input_lra', 'input_thresh', 'target_offset'];

    #[\NoDiscard]
    public function parse(string $stderr): ?LoudnessMeasurement
    {
        if (preg_match_all('/\{[^{}]*"input_i"[^{}]*\}/s', $stderr, $matches) === 0) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) array_last($matches[0]), true);

        if (! is_array($decoded)) {
            return null;
        }

        $values = [];

        foreach (self::REQUIRED as $key) {
            $value = $decoded[$key] ?? null;

            // `-inf` is a string, not a number: a silent recording must not be
            // handed to a linear pass that would try to raise it to the target.
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                return null;
            }

            if (! is_numeric($value)) {
                return null;
            }

            $values[] = (float) $value;
        }

        // Below -60 LUFS there is effectively no programme to normalize. The
        // gain needed to reach -16 would amplify the noise floor, not the voice.
        if ($values[0] <= -60.0) {
            return null;
        }

        return new LoudnessMeasurement(...$values);
    }
}
