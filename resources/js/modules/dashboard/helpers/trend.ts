import { Minus, TrendingDown, TrendingUp } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { TrendDirection, TrendPolarity } from '../types';

export type TrendPresentation = {
    direction: TrendDirection;
    /** Arrow that states the direction without relying on colour. */
    icon: LucideIcon;
    /** Token-backed text colour for the delta. */
    class: string;
    /** Screen-reader wording; the visual text is the signed percentage. */
    srLabel: string;
};

export function trendDirection(changePercent: number): TrendDirection {
    if (changePercent > 0) {
        return 'up';
    }

    if (changePercent < 0) {
        return 'down';
    }

    return 'flat';
}

/**
 * Turns a signed change into an arrow, a colour and a spoken label.
 *
 * Polarity is what decides the colour: more revenue is good, more overdue
 * invoices is not, and both can be "up". The arrow and the signed number carry
 * the same information independently of colour (WCAG 1.4.1).
 */
export function trendPresentation(
    changePercent: number,
    polarity: TrendPolarity,
): TrendPresentation {
    const direction = trendDirection(changePercent);

    if (direction === 'flat') {
        return {
            direction,
            icon: Minus,
            class: 'text-muted-foreground',
            srLabel: 'unchanged',
        };
    }

    const isGood =
        polarity === 'positive-up' ? direction === 'up' : direction === 'down';

    return {
        direction,
        icon: direction === 'up' ? TrendingUp : TrendingDown,
        class: isGood ? 'text-success' : 'text-destructive',
        srLabel: `${direction === 'up' ? 'up' : 'down'}, ${isGood ? 'improving' : 'worsening'}`,
    };
}
