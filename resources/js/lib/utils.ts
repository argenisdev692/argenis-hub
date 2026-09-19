import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * An externally sourced URL (scraped pages, job feeds, LLM output) that is
 * safe to bind to `:href`, or `null`. Vue does not sanitize `href`, so a
 * `javascript:` / `data:` value would execute in the admin session on click.
 */
export function safeExternalUrl(url: string | null | undefined): string | null {
    if (url === null || url === undefined || !URL.canParse(url)) {
        return null;
    }

    const { protocol } = new URL(url);

    return protocol === 'https:' || protocol === 'http:' ? url : null;
}
