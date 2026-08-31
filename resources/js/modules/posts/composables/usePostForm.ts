import { useAppForm } from '@/common/form';
import { store, update } from '@/routes/posts';
import {
    emptyPostFormValues,
    postFormSchema,
    toPostFormValues,
    toWritePayload,
} from '../schemas/postFormSchema';
import type { PostFormValues } from '../schemas/postFormSchema';
import type { GeneratedPostContent, PostDetail } from '../types';

export type PostFormOptions = {
    /**
     * `null` on the Create page, the record on Edit. Read once: unlike the
     * dialog-based modules, these are dedicated pages, so the mode is fixed for
     * the lifetime of the form and there is no re-seeding to do.
     */
    post?: PostDetail | null;
};

/**
 * The Create/Edit form.
 *
 * ## Why POST-with-`_method` on update
 *
 * The payload can carry a cover image, and PHP does not populate `$_POST` (or
 * `$_FILES`) for a multipart `PUT`. Inertia does not spoof the method on its
 * own — its docs are explicit that this is the caller's job — so the update
 * goes out as a POST carrying `_method: 'put'`, which Laravel unwraps before
 * routing and resolves to the real `PUT /posts/{uuid}`.
 *
 * `forceFormData` is on unconditionally rather than only when a file is
 * present, so the request shape does not change underneath the server depending
 * on whether the user touched the dropzone. The empty strings that multipart
 * encoding produces for absent values are turned back into nulls by Laravel's
 * `ConvertEmptyStringsToNull` global middleware, which is what keeps
 * `nullable` fields nullable.
 */
export function usePostForm({ post = null }: PostFormOptions = {}) {
    const form = useAppForm({
        defaultValues: post ? toPostFormValues(post) : emptyPostFormValues(),
        schema: postFormSchema,
        submit: {
            target: post
                ? { url: update(post.uuid).url, method: 'post' }
                : store(),
            transform: (values: PostFormValues) => ({
                ...toWritePayload(values),
                ...(post ? { _method: 'put' } : {}),
            }),
            forceFormData: true,
            successMessage: post ? 'Post updated.' : 'Post created.',
        },
    });

    /**
     * Fills the form from an AI draft, provenance included.
     *
     * Deliberately does NOT touch `category_uuid`, `status` or `scheduled_at`:
     * those are editorial decisions the user made before pressing generate, and
     * silently resetting them is the fastest way to publish something that was
     * meant to stay a draft.
     */
    function applyGeneratedDraft(draft: GeneratedPostContent): void {
        form.setFieldValue('title', draft.title);
        form.setFieldValue('content', normalizeGeneratedHtml(draft.content));
        form.setFieldValue('excerpt', draft.excerpt);
        form.setFieldValue('meta_title', draft.meta_title);
        form.setFieldValue('meta_description', draft.meta_description);
        form.setFieldValue('meta_keywords', draft.meta_keywords);
        form.setFieldValue('cover_image_path', draft.cover_image_path ?? '');
        form.setFieldValue('is_ai_generated', true);
        form.setFieldValue('ai_provider', draft.provider);
        form.setFieldValue('seo_score', draft.seo_score);
        form.setFieldValue('eeat_score', draft.eeat_score);
        form.setFieldValue('human_writing_index', draft.human_writing_index);
        form.setFieldValue('ai_detection_risk', draft.ai_detection_risk);
        form.setFieldValue('ai_scores', draft.scores);
    }

    return { form, applyGeneratedDraft };
}

/**
 * A safety net for the one thing a language model can still get wrong here.
 *
 * `GeneratePostContentAgent` instructs the model to answer in semantic HTML,
 * and it normally does. But "normally" is the operative word with a generative
 * contract, and the failure is silent and ugly: raw Markdown dropped into an
 * HTML editor shows up on screen as the literal characters `## Heading`.
 *
 * So: strip a stray code fence (the other common slip), and if what is left
 * carries no block-level tag at all, treat it as plain text and paragraph it on
 * blank lines. Anything that already looks like HTML passes through untouched —
 * this is a fallback, not a second parser.
 */
function normalizeGeneratedHtml(content: string): string {
    const unfenced = content
        .trim()
        .replace(/^```(?:html)?\s*/i, '')
        .replace(/\s*```$/, '')
        .trim();

    if (/<(?:p|h[1-6]|ul|ol|blockquote|pre|div)\b/i.test(unfenced)) {
        return unfenced;
    }

    return unfenced
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter(Boolean)
        .map((block) => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
