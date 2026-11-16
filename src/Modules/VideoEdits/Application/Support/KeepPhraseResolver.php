<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;

/**
 * Merges the phrases this installation always keeps with the ones the owner
 * typed for one video.
 *
 * The standing list is policy, not a default: "observemos", "muy bien", "vamos a
 * enviar" and "enviemos" hold a lesson together, and a request that omits them —
 * or a form that never had the field — must not thereby lose them. So the union
 * is computed in the backend, and again when the guard runs, which is what
 * protects edits recorded before the list existed.
 *
 * Duplicates are folded by normalised form, so typing "Muy bien" next to the
 * standing "muy bien" does not produce two entries that mean one thing.
 */
final readonly class KeepPhraseResolver
{
    public function __construct(private Config $config) {}

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    #[\NoDiscard]
    public function resolve(array $requested): array
    {
        $seen = [];
        $phrases = [];

        foreach ([...$this->standing(), ...$requested] as $phrase) {
            if (! is_string($phrase)) {
                continue;
            }

            $trimmed = trim($phrase);

            if ($trimmed === '') {
                continue;
            }

            $key = self::key($trimmed);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $phrases[] = $trimmed;
        }

        return $phrases;
    }

    /**
     * @return list<string>
     */
    private function standing(): array
    {
        $configured = (array) $this->config->get('video-edit.ai.always_keep_phrases', []);

        return array_values(array_filter($configured, is_string(...)));
    }

    private static function key(string $phrase): string
    {
        return trim(Str::lower(Str::ascii($phrase)));
    }
}
