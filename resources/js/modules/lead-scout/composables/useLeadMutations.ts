import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { update as updateAiSettingsRoute } from '@/routes/lead-scout/ai-settings';
import { update as updateBudgetsRoute } from '@/routes/lead-scout/budgets';
import { update as updateChannelRoute } from '@/routes/lead-scout/channels';
import {
    objection,
    store as storeContact,
    update as updateContact,
} from '@/routes/lead-scout/contacts';
import { drafts, rescore } from '@/routes/lead-scout/leads';
import { reply, update as updateOutreachRoute } from '@/routes/lead-scout/outreaches';
import { store as storeSuppression } from '@/routes/lead-scout/suppressions';
import type { DraftPayload, StagePayload } from '../types';
import { LEAD_KEY } from './useLead';
import { LEADS_KEY } from './useLeads';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

function invalidateLead(queryCache: ReturnType<typeof useQueryCache>, uuid?: string) {
    const invalidations = [queryCache.invalidateQueries({ key: LEADS_KEY })];

    if (uuid !== undefined) {
        invalidations.push(queryCache.invalidateQueries({ key: LEAD_KEY(uuid) }));
    }

    return Promise.all(invalidations);
}

export function useLeadMutations() {
    const queryCache = useQueryCache();

    const rescoreLead = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(rescore(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Lead re-scored.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to re-score the lead.'));
        },
        onSettled: (_data, _error, uuid) =>
            invalidateLead(queryCache, uuid),
    });

    const generateDraft = useMutation<
        {
            data: { uuid: string };
            meta: { subject: string; unconfirmed_claims: string[] };
        },
        { uuid: string; payload: DraftPayload }
    >({
        mutation: ({ uuid, payload }: { uuid: string; payload: DraftPayload }) =>
            httpJson<{
                data: { uuid: string };
                meta: { subject: string; unconfirmed_claims: string[] };
            }>(toUrl(drafts(uuid)), { method: 'POST', body: payload }),
        onSuccess() {
            toast.success('Draft generated. Review before sending — nothing is sent automatically.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to generate the draft.'));
        },
        onSettled: (data, _error, variables) =>
            invalidateLead(queryCache, variables.uuid),
    });

    const updateOutreach = useMutation({
        mutation: ({ uuid, payload }: { uuid: string; payload: StagePayload }) =>
            httpJson<{ data: unknown; meta: { daily_sent: number; daily_limit_warning: boolean } }>(
                toUrl(updateOutreachRoute(uuid)),
                { method: 'PATCH', body: payload },
            ),
        onSuccess(data) {
            if (data.meta.daily_limit_warning) {
                toast.warning(
                    `Over 15 manual sends today (${data.meta.daily_sent}). Slow down — quality beats volume.`,
                );
            } else {
                toast.success('Outreach updated.');
            }
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the outreach.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    const recordReply = useMutation({
        mutation: ({
            uuid,
            outcome,
            notes,
        }: {
            uuid: string;
            outcome: 'interested' | 'not_interested' | 'unsubscribe';
            notes?: string;
        }) =>
            httpJson<unknown>(toUrl(reply(uuid)), {
                method: 'POST',
                body: { outcome, notes },
            }),
        onSuccess() {
            toast.success('Reply recorded.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to record the reply.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    const upsertContact = useMutation({
        mutation: ({
            companyUuid,
            contactUuid,
            payload,
        }: {
            companyUuid: string;
            contactUuid?: string;
            payload: Record<string, unknown>;
        }) =>
            contactUuid === undefined
                ? httpJson<unknown>(toUrl(storeContact(companyUuid)), {
                      method: 'POST',
                      body: payload,
                  })
                : httpJson<unknown>(toUrl(updateContact(contactUuid)), {
                      method: 'PATCH',
                      body: payload,
                  }),
        onSuccess() {
            toast.success('Decision-maker saved.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to save the decision-maker.'));
        },
        onSettled: (_data, _error, variables) =>
            invalidateLead(queryCache, variables.companyUuid),
    });

    const objectContact = useMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(objection(uuid)), { method: 'POST' }),
        onSuccess() {
            toast.success('Opposition recorded and contact anonymized.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to record the opposition.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    const updateChannel = useMutation({
        mutation: ({ uuid, status }: { uuid: string; status: 'active' | 'broken' }) =>
            httpJson<unknown>(toUrl(updateChannelRoute(uuid)), {
                method: 'PATCH',
                body: { status },
            }),
        onSuccess() {
            toast.success('Channel updated.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the channel.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    const updateBudgets = useMutation({
        mutation: (budgets: { category: string; limit_eur: number }[]) =>
            httpJson<unknown>(toUrl(updateBudgetsRoute()), {
                method: 'PUT',
                body: { budgets },
            }),
        onSuccess() {
            toast.success('Budgets updated.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update budgets.'));
        },
    });

    const updateAiSettings = useMutation({
        mutation: (payload: Record<string, unknown>) =>
            httpJson<unknown>(toUrl(updateAiSettingsRoute()), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('AI defaults updated.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update AI defaults.'));
        },
    });

    const suppressCompany = useMutation({
        mutation: (payload: { domain: string; reason: string }) =>
            httpJson<unknown>(toUrl(storeSuppression()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Company suppressed.');
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to suppress the company.'));
        },
        onSettled: () => queryCache.invalidateQueries({ key: LEADS_KEY }),
    });

    return {
        rescoreLead,
        generateDraft,
        updateOutreach,
        recordReply,
        upsertContact,
        objectContact,
        updateChannel,
        updateBudgets,
        updateAiSettings,
        suppressCompany,
    };
}
