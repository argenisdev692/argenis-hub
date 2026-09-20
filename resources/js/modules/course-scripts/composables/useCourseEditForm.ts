import type { Ref } from 'vue';
import { watch } from 'vue';
import type { StandardSchema } from '@/common/form';
import { applyHttpErrors, useAppForm } from '@/common/form';

/**
 * The shape every course-page edit sheet shares: re-seed the form from the
 * current record each time the sheet opens, submit through a Pinia Colada
 * mutation, put 422s back on their fields, close on success.
 *
 * `seed` is a getter, read on every open, so the form always starts from the
 * latest Inertia prop — never from values left over by a cancelled edit.
 */
export function useCourseEditForm<
    TValues extends Record<string, unknown>,
>(options: {
    open: Ref<boolean>;
    schema: StandardSchema;
    seed: () => TValues;
    submit: (values: TValues) => Promise<unknown>;
}) {
    const { open, schema, seed, submit } = options;

    const form = useAppForm({
        defaultValues: seed(),
        schema,
        onSubmit: async (values) => {
            try {
                await submit(values);
            } catch (error) {
                // A 422 lands on its fields; anything else was toasted by the
                // mutation. Either way the sheet stays open for another try.
                applyHttpErrors(form, error);

                return;
            }

            open.value = false;
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            form.reset(seed());
        }
    });

    return form;
}
