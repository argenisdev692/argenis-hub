<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

/**
 * Builds FFmpeg filtergraphs from validated numbers only (OWASP §3 · AD-4, AD-5).
 *
 * Both graphs end in the labels `[outv]` / `[outa]`, normalized to the output
 * profile. Numbers are formatted with `%F` so the locale can never turn a
 * decimal point into a comma.
 */
final readonly class FilterGraphBuilder
{
    public const string VIDEO_OUTPUT = '[outv]';

    public const string AUDIO_OUTPUT = '[outa]';

    /**
     * Length of the audio fade on each side of a join. A hard cut between two
     * waveforms that are not at zero clicks; 15 ms is too short to hear as a
     * fade but long enough to remove the click.
     */
    public const int JOIN_FADE_MS = 15;

    /**
     * Normalize every input to the profile (scale + pad, never stretch) and join
     * them in order. Inputs without audio get generated silence of their length.
     *
     * @param  list<MediaProbe>  $inputProbes
     */
    #[\NoDiscard]
    public function merge(array $inputProbes, OutputProfile $profile): string
    {
        $parts = [];
        $pairs = '';

        foreach ($inputProbes as $index => $probe) {
            $parts[] = sprintf('[%d:v:0]%s[v%d]', $index, $this->videoNormalization($profile), $index);
            $parts[] = $probe->hasAudio
                ? sprintf('[%d:a:0]%s[a%d]', $index, $this->audioNormalization($profile), $index)
                : sprintf('%s[a%d]', $this->silence($profile, $probe->durationMs), $index);
            $pairs .= sprintf('[v%d][a%d]', $index, $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=1:a=1%s%s', $pairs, count($inputProbes), self::VIDEO_OUTPUT, self::AUDIO_OUTPUT);

        return implode(';', $parts);
    }

    /**
     * Keep only the given ranges of a single input, frame- and sample-accurately
     * (trim/atrim + reset timestamps + concat), then normalize to the profile.
     * Every audio join gets a {@see self::JOIN_FADE_MS} fade so cuts do not click.
     *
     * @param  list<TimeRange>  $keepRanges
     */
    #[\NoDiscard]
    public function keepRanges(array $keepRanges, MediaProbe $inputProbe, OutputProfile $profile): string
    {
        $parts = [];
        $pairs = '';

        foreach ($keepRanges as $index => $range) {
            $parts[] = sprintf(
                '[0:v:0]trim=start=%s:end=%s,setpts=PTS-STARTPTS[v%d]',
                self::seconds($range->startMs),
                self::seconds($range->endMs),
                $index,
            );
            $parts[] = $inputProbe->hasAudio
                ? sprintf(
                    '[0:a:0]atrim=start=%s:end=%s,asetpts=PTS-STARTPTS%s[a%d]',
                    self::seconds($range->startMs),
                    self::seconds($range->endMs),
                    self::joinFades($range, fadeIn: $index > 0, fadeOut: $index < count($keepRanges) - 1),
                    $index,
                )
                : sprintf('%s[a%d]', $this->silence($profile, $range->durationMs()), $index);
            $pairs .= sprintf('[v%d][a%d]', $index, $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=1:a=1[cv][ca]', $pairs, count($keepRanges));
        $parts[] = sprintf('[cv]%s%s', $this->videoNormalization($profile), self::VIDEO_OUTPUT);
        $parts[] = sprintf('[ca]%s%s', $this->audioNormalization($profile), self::AUDIO_OUTPUT);

        return implode(';', $parts);
    }

    /**
     * Fades only the edges that meet another range: the very start and end of
     * the recording are not joins and keep their original attack and decay.
     */
    private static function joinFades(TimeRange $range, bool $fadeIn, bool $fadeOut): string
    {
        $fadeMs = min(self::JOIN_FADE_MS, intdiv($range->durationMs(), 2));

        if ($fadeMs <= 0) {
            return '';
        }

        return ($fadeIn ? sprintf(',afade=t=in:st=0:d=%s', self::seconds($fadeMs)) : '')
            .($fadeOut ? sprintf(',afade=t=out:st=%s:d=%s', self::seconds($range->durationMs() - $fadeMs), self::seconds($fadeMs)) : '');
    }

    private function videoNormalization(OutputProfile $profile): string
    {
        return sprintf(
            'scale=%1$d:%2$d:force_original_aspect_ratio=decrease,pad=%1$d:%2$d:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=%3$s,format=yuv420p',
            $profile->width,
            $profile->height,
            sprintf('%.3F', $profile->frameRate),
        );
    }

    private function audioNormalization(OutputProfile $profile): string
    {
        return sprintf(
            'aresample=%d:async=1,aformat=sample_rates=%d:channel_layouts=%s',
            $profile->audioSampleRate,
            $profile->audioSampleRate,
            self::channelLayout($profile),
        );
    }

    private function silence(OutputProfile $profile, int $durationMs): string
    {
        return sprintf(
            'anullsrc=channel_layout=%s:sample_rate=%d,atrim=duration=%s',
            self::channelLayout($profile),
            $profile->audioSampleRate,
            self::seconds($durationMs),
        );
    }

    private static function channelLayout(OutputProfile $profile): string
    {
        return $profile->audioChannels === 1 ? 'mono' : 'stereo';
    }

    private static function seconds(int $milliseconds): string
    {
        return sprintf('%.3F', $milliseconds / 1000);
    }
}
