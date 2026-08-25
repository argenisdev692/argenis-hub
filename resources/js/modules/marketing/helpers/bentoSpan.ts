import type { BentoSpan } from '../types';

/**
 * Bento span → grid classes on a 6-column `lg` grid.
 *
 * Literal strings for the same reason as `toneClasses`: Tailwind scans source
 * text, so interpolated class names never reach the stylesheet.
 */
const SPAN_CLASSES: Readonly<Record<BentoSpan, string>> = {
    default: 'lg:col-span-2',
    wide: 'lg:col-span-4',
    tall: 'lg:col-span-2 lg:row-span-2',
} as const;

export function bentoSpanClass(span: BentoSpan): string {
    return SPAN_CLASSES[span];
}
