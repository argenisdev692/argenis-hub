import DOMPurify from 'dompurify';

/**
 * Sanitation for the rich-text HTML the post editor produces.
 *
 * `post_content` round-trips real HTML — Tiptap writes it, the column stores it
 * verbatim, and every surface that displays it has to reach for `v-html`, which
 * is exactly the sink OWASP A03 is about. So everything is washed through here
 * on the way OUT, not just on the way in: the database is not a trust boundary.
 * A row written by an older build, edited straight in SQL, or filled from an AI
 * provider's response is no more trustworthy than something typed into a form.
 *
 * The allowlist mirrors what the toolbar can actually produce, and nothing
 * else. If the editor cannot create it, it has no business surviving
 * sanitation — no `<img>`, no `<iframe>`, no `<style>`, no `<form>`, and no
 * event-handler attributes, which is what keeps this list short enough to audit
 * at a glance instead of merely long enough to look thorough.
 */

/** Exactly the nodes and marks `RichTextInput`'s toolbar can emit. */
const ALLOWED_TAGS = [
    'p',
    'br',
    'strong',
    'em',
    'u',
    's',
    'code',
    'pre',
    'h2',
    'h3',
    'h4',
    'ul',
    'ol',
    'li',
    'blockquote',
    'a',
    'hr',
] as const;

const ALLOWED_ATTR = ['href', 'title', 'target', 'rel'] as const;

/**
 * Schemes an anchor may use. DOMPurify already drops `javascript:`, but the
 * default pattern also permits `tel:`, `callto:`, `sms:` and a handful of
 * others that a blog body has no reason to contain — an allowlist of four beats
 * a denylist of the ones we happened to think of.
 */
const ALLOWED_URI_REGEXP = /^(?:https?:|mailto:|[#/])/i;

let hooksInstalled = false;

/**
 * DOMPurify hooks are global and additive, so registering on every call would
 * stack duplicates on a page that renders many posts. Installed lazily rather
 * than at import time so the module stays inert (and SSR-safe) until something
 * actually sanitizes.
 */
function installHooks(): void {
    if (hooksInstalled) {
        return;
    }

    hooksInstalled = true;

    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (!(node instanceof Element) || node.tagName !== 'A') {
            return;
        }

        // Applied after sanitation, so it also covers links pasted in with a
        // hand-written `target="_self"` or no `rel` at all. `noopener` is the
        // one that matters (reverse tabnabbing); `nofollow` keeps a spammed
        // comment-style link from passing this site's ranking on.
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer nofollow');
    });
}

/**
 * Returns HTML safe to hand to `v-html`.
 *
 * Yields an empty string outside the browser: DOMPurify needs a real DOM, and
 * silently returning the unsanitized input on the server would be the one
 * failure mode this function exists to prevent.
 */
export function sanitizeRichText(html: string | null | undefined): string {
    if (!html || typeof window === 'undefined') {
        return '';
    }

    installHooks();

    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: [...ALLOWED_TAGS],
        ALLOWED_ATTR: [...ALLOWED_ATTR],
        ALLOWED_URI_REGEXP,
    });
}
