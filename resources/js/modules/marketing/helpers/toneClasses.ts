import type { BrandTone } from '../types';

type ToneClasses = {
    /** Tinted, low-opacity fill for the icon chip. */
    readonly chip: string;
    /** Icon colour — always paired with `chip`, never used on body text. */
    readonly icon: string;
    /** Hairline that picks up the tone on hover. */
    readonly ring: string;
};

/**
 * Brand hue → token-backed utility classes.
 *
 * Kept as a lookup instead of string interpolation because Tailwind only sees
 * class names that appear literally in source; `text-brand-${tone}` would be
 * purged out of the build.
 *
 * Every class here resolves to a design token registered in `app.css`
 * (`--color-brand-*`, `--color-accent-gold`), so none of them is a raw palette
 * colour.
 */
const TONE_CLASSES: Readonly<Record<BrandTone, ToneClasses>> = {
    cyan: {
        chip: 'bg-brand-cyan/12',
        icon: 'text-brand-cyan',
        ring: 'group-hover:border-brand-cyan/40',
    },
    purple: {
        chip: 'bg-brand-purple/12',
        icon: 'text-brand-purple',
        ring: 'group-hover:border-brand-purple/40',
    },
    magenta: {
        chip: 'bg-brand-magenta/12',
        icon: 'text-brand-magenta',
        ring: 'group-hover:border-brand-magenta/40',
    },
    indigo: {
        chip: 'bg-brand-indigo/12',
        icon: 'text-brand-indigo',
        ring: 'group-hover:border-brand-indigo/40',
    },
    gold: {
        chip: 'bg-accent-gold/12',
        icon: 'text-accent-gold',
        ring: 'group-hover:border-accent-gold/40',
    },
} as const;

export function toneClasses(tone: BrandTone): ToneClasses {
    return TONE_CLASSES[tone];
}
