import { useAppForm } from '@/common/form';
import { update } from '@/routes/social-media';
import {
    socialMediaContentFormSchema,
    toSocialMediaContentFormValues,
    toWritePayload,
} from '../schemas/socialMediaContentFormSchema';
import type { SocialMediaContentFormValues } from '../schemas/socialMediaContentFormSchema';
import type { SocialMediaContentDetail } from '../types';

export type SocialMediaContentFormOptions = {
    /**
     * The package under review. Read once: this is a dedicated page, not a
     * dialog, so the record is fixed for the lifetime of the form and there is
     * no re-seeding to do.
     */
    content: SocialMediaContentDetail;
};

/**
 * The review/edit form.
 *
 * Edit-only by design — there is no `store` route. Content is always AI-born
 * through `useSocialMediaAi`, and this form is the human pass over the result:
 * fix the copy, set the lifecycle, schedule it.
 *
 * Submitted as a real `PUT` (not the POST-with-`_method` dance `usePostForm`
 * needs): this payload carries no file, so there is no multipart body for PHP
 * to drop.
 *
 * Per-platform copy is intentionally not editable here — the backend DTO does
 * not accept it, because a hand-edited platform variant would silently
 * disagree with the scores that were computed from the generated one. Changing
 * it means re-running generation.
 */
export function useSocialMediaContentForm({
    content,
}: SocialMediaContentFormOptions) {
    return useAppForm({
        defaultValues: toSocialMediaContentFormValues(content),
        schema: socialMediaContentFormSchema,
        submit: {
            target: update(content.uuid),
            transform: (values: SocialMediaContentFormValues) =>
                toWritePayload(values),
            successMessage: 'Content updated.',
        },
    });
}
