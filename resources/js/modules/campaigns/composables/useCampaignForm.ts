import { useAppForm } from '@/common/form';
import { update } from '@/routes/campaigns';
import {
    campaignFormSchema,
    toCampaignFormValues,
    toWritePayload,
} from '../schemas/campaignFormSchema';
import type { CampaignFormValues } from '../schemas/campaignFormSchema';
import type { CampaignDetail } from '../types';

export type CampaignFormOptions = {
    /**
     * The campaign under review. Read once: this is a dedicated page, not a
     * dialog, so the record is fixed for the lifetime of the form and there is
     * no re-seeding to do.
     */
    campaign: CampaignDetail;
};

/**
 * The review/edit form.
 *
 * Edit-only by design — there is no `store` route. A campaign is always AI-born
 * through `useCampaignAi`, and this form is the human pass over the result: fix
 * the copy, set the lifecycle, schedule it.
 *
 * Submitted as a real `PUT` (not the POST-with-`_method` dance `usePostForm`
 * needs): this payload carries no file, so there is no multipart body for PHP
 * to drop.
 *
 * Per-platform copy is intentionally not editable here — `UpdateCampaignData`
 * does not accept it, because a hand-edited platform variant would silently
 * disagree with the five scores that were computed from the generated one.
 * Changing it means re-running generation.
 */
export function useCampaignForm({ campaign }: CampaignFormOptions) {
    return useAppForm({
        defaultValues: toCampaignFormValues(campaign),
        schema: campaignFormSchema,
        submit: {
            target: update(campaign.uuid),
            transform: (values: CampaignFormValues) => toWritePayload(values),
            successMessage: 'Campaign updated.',
        },
    });
}
