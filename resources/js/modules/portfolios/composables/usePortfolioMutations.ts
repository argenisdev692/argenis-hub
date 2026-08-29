import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/portfolios/admin';
import type { Portfolio, PortfolioWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const PORTFOLIOS_KEY = ['portfolios'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function usePortfolioMutations() {
    const queryCache = useQueryCache();

    const createPortfolio = useMutation({
        mutation: (payload: PortfolioWritePayload) =>
            httpJson<Portfolio>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Portfolio created.');
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the portfolio.'));
        },
    });

    const updatePortfolio = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: PortfolioWritePayload;
        }) =>
            httpJson<Portfolio>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('Portfolio updated.');
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the portfolio.'));
        },
    });

    const deletePortfolio = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Portfolio deleted.');
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the portfolio.'));
        },
    });

    const restorePortfolio = useMutation({
        mutation: (uuid: string) =>
            httpJson<Portfolio>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Portfolio restored.');
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the portfolio.'),
            );
        },
    });

    const bulkDeletePortfolios = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'portfolio' : 'portfolios'} deleted.`,
            );
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to delete the selected portfolios.',
                ),
            );
        },
    });

    const bulkRestorePortfolios = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'portfolio' : 'portfolios'} restored.`,
            );
            queryCache.invalidateQueries({ key: PORTFOLIOS_KEY });
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(
                    error,
                    'Failed to restore the selected portfolios.',
                ),
            );
        },
    });

    return {
        createPortfolio,
        updatePortfolio,
        deletePortfolio,
        restorePortfolio,
        bulkDeletePortfolios,
        bulkRestorePortfolios,
    };
}
