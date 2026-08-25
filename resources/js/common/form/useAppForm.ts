import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { AnyFieldApi } from '@tanstack/vue-form';
import { useForm } from '@tanstack/vue-form';
import { toast } from 'vue-sonner';

/**
 * A Standard Schema v1 validator. Zod 4, Valibot and ArkType all implement this
 * interface, so the form layer never depends on a specific validation library.
 *
 * @see https://github.com/standard-schema/standard-schema
 */
export type StandardSchema<TInput = unknown, TOutput = TInput> = {
    readonly '~standard': {
        readonly version: 1;
        readonly vendor: string;
        readonly validate: (
            value: unknown,
        ) =>
            | { value: TOutput; issues?: undefined }
            | { issues: ReadonlyArray<{ message: string }> }
            | Promise<
                  | { value: TOutput; issues?: undefined }
                  | { issues: ReadonlyArray<{ message: string }> }
              >;
    };
};

/**
 * Either a plain URL or the object a Wayfinder action returns
 * (`store()` → `{ url: '/things', method: 'post' }`).
 */
export type FormTarget = string | { url: string; method: string };

export type HttpMethod = 'post' | 'put' | 'patch' | 'delete';

export type InertiaSubmitOptions<TValues extends Record<string, unknown>> = {
    /** Where to send it — a URL, or a Wayfinder action result. */
    target: FormTarget;
    /** Overrides the method carried by a Wayfinder target. */
    method?: HttpMethod;
    /** Reshape values before they go over the wire (File objects, nested DTOs, …). */
    transform?: (values: TValues) => Record<string, unknown>;
    /** Toast shown on a successful round-trip. Pass `null` to stay silent. */
    successMessage?: string | null;
    /** Send as multipart/form-data — required when the payload carries a File. */
    forceFormData?: boolean;
    onSuccess?: () => void;
};

type FieldMetaWithErrorMap = {
    errorMap?: Record<string, unknown>;
    isTouched?: boolean;
};

/**
 * The one method `applyServerErrors` needs. Declared structurally because
 * `FormApi.setFieldMeta` is keyed to `DeepKeys<TFormData>`, and Laravel hands
 * us plain runtime strings that TypeScript cannot narrow to that union.
 */
type FieldMetaWriter = {
    setFieldMeta: (name: never, updater: never) => void;
};

function resolveTarget(target: FormTarget): {
    url: string;
    method: HttpMethod;
} {
    if (typeof target === 'string') {
        return { url: target, method: 'post' };
    }

    return {
        url: target.url,
        method: target.method.toLowerCase() as HttpMethod,
    };
}

/**
 * True once the user has actually interacted with the field, so a pristine form
 * never opens covered in red. Drives both `data-invalid` and `aria-invalid`.
 */
export function isFieldInvalid(field: AnyFieldApi): boolean {
    return field.state.meta.isTouched && !field.state.meta.isValid;
}

/** Flattens a field's error map into the shape `<FieldError>` expects. */
export function fieldErrorMessages(field: AnyFieldApi): string[] {
    return field.state.meta.errors
        .map((error: unknown) => {
            if (typeof error === 'string') {
                return error;
            }

            if (error && typeof error === 'object' && 'message' in error) {
                return String((error as { message: unknown }).message);
            }

            return null;
        })
        .filter((message): message is string => Boolean(message));
}

/**
 * Projects Laravel's `errors` bag onto the matching TanStack fields, using the
 * dedicated `onServer` slot so a later client-side pass clears them naturally
 * instead of leaving a stale message behind.
 *
 * Laravel reports nested keys as `address.city` and `items.0.name`, which is
 * already TanStack's own path syntax, so no translation is needed.
 */
export function applyServerErrors(
    form: FieldMetaWriter,
    errors: Record<string, string>,
): void {
    for (const [name, message] of Object.entries(errors)) {
        form.setFieldMeta(
            name as never,
            ((meta: FieldMetaWithErrorMap) => ({
                ...meta,
                isTouched: true,
                errorMap: { ...meta.errorMap, onServer: message },
            })) as never,
        );
    }
}

/**
 * `useForm` from TanStack, plus the two things every form in this app needs:
 * Standard Schema validation (Zod 4 passes straight through — no adapter) and
 * an Inertia submit that maps 422 responses back onto the offending fields.
 *
 * @example
 * const form = useAppForm({
 *     defaultValues: { name: '', email: '' },
 *     schema: z.object({ name: z.string().min(2), email: z.email() }),
 *     submit: { target: store(), successMessage: 'Client created.' },
 * });
 */
export function useAppForm<TValues extends Record<string, unknown>>(options: {
    defaultValues: TValues;
    schema?: StandardSchema;
    /** Validate on blur as well as on submit. Defaults to true. */
    validateOnBlur?: boolean;
    /** Inertia submission. Omit it and supply `onSubmit` to handle it yourself. */
    submit?: InertiaSubmitOptions<TValues>;
    onSubmit?: (values: TValues) => Promise<void> | void;
}) {
    const {
        defaultValues,
        schema,
        validateOnBlur = true,
        submit,
        onSubmit,
    } = options;

    const form = useForm({
        defaultValues,
        validators: {
            ...(schema ? { onSubmit: schema } : {}),
            ...(schema && validateOnBlur ? { onBlur: schema } : {}),
        },
        onSubmit: async ({ value }: { value: TValues }) => {
            if (onSubmit) {
                await onSubmit(value);

                return;
            }

            if (!submit) {
                return;
            }

            const { url, method } = resolveTarget(submit.target);
            const payload = (
                submit.transform ? submit.transform(value) : value
            ) as RequestPayload;

            await new Promise<void>((resolve) => {
                router.visit(url, {
                    method: submit.method ?? method,
                    data: payload,
                    forceFormData: submit.forceFormData,
                    preserveScroll: true,
                    onSuccess: () => {
                        if (submit.successMessage !== null) {
                            toast.success(submit.successMessage ?? 'Saved.');
                        }

                        submit.onSuccess?.();
                    },
                    onError: (errors: Record<string, string>) => {
                        applyServerErrors(form, errors);
                    },
                    onFinish: () => resolve(),
                });
            });
        },
    });

    return form;
}
