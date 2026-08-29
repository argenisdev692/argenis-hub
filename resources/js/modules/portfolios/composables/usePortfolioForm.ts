import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    emptyPortfolioFormValues,
    portfolioFormSchema,
    toPortfolioFormValues,
    toWritePayload,
} from '../schemas/portfolioFormSchema';
import type { Portfolio } from '../types';
import { usePortfolioMutations } from './usePortfolioMutations';

export type PortfolioFormOptions = {
    /** The sheet's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else means
     * "edit": the form seeds from the record and submits to `PUT`.
     */
    portfolio: () => Portfolio | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at submit
 * time by whether `portfolio()` is still non-null — the row being edited never
 * changes out from under an open sheet, so reading it once at that point is
 * equivalent to and simpler than threading a separate "mode" flag alongside it.
 */
export function usePortfolioForm({
    open,
    portfolio,
    onSuccess,
}: PortfolioFormOptions) {
    const { createPortfolio, updatePortfolio } = usePortfolioMutations();

    const form = useAppForm({
        defaultValues: emptyPortfolioFormValues(),
        schema: portfolioFormSchema,
        onSubmit: async (values) => {
            const payload = toWritePayload(values);
            const current = portfolio();

            try {
                if (current) {
                    await updatePortfolio.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createPortfolio.mutateAsync(payload);
                }
            } catch {
                // The mutation's own `onError` already toasted the failure;
                // swallow it here so it never reaches `handleSubmit`'s caller
                // (`FormSheet` awaits it without a try/catch) and leaves the
                // sheet open for another attempt.
                return;
            }

            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            const current = portfolio();

            form.reset(
                current
                    ? toPortfolioFormValues(current)
                    : emptyPortfolioFormValues(),
            );
        }
    });

    return form;
}
