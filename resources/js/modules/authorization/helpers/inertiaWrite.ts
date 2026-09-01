import type { Method, RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';

/**
 * A promisified Inertia write, so this module's mutations can be Pinia Colada
 * `useMutation`s like every other module's.
 *
 * Why not `lib/http.ts` like Clients and Services? Because those modules expose
 * a JSON CRUD surface under `/data/admin/*`, and this one does not:
 * `RoleController::store/update/destroy/restore/bulk*` all answer
 * `back()->with(...)`, an Inertia redirect. A bare `fetch()` would follow that
 * 302 into the Inertia page response and try to parse it as the created record.
 *
 * This is exactly case 1 of the `FRONTEND/SKILL.md` §5 decision tree ("does the
 * action refresh page props? → Inertia `router`"), wrapped so the calling
 * composable still gets `mutateAsync` / `isLoading` / cache invalidation.
 *
 * Deliberately module-local: promote it to `lib/` only once a second module
 * needs it, rather than growing the shared surface for one caller.
 */

/** A Wayfinder action result — `store()` → `{ url: '/roles', method: 'post' }`. */
export type InertiaWriteTarget = { url: string; method: string };

/** Carries Laravel's 422 bag so a form can project it back onto its fields. */
export class InertiaWriteError extends Error {
    constructor(
        message: string,
        public readonly errors: Record<string, string> = {},
    ) {
        super(message);
    }
}

function firstMessage(errors: Record<string, string>): string | null {
    const [message] = Object.values(errors);

    return message ?? null;
}

/**
 * Sends `data` to `target` and resolves once Inertia has swapped the page in.
 *
 * `preserveState` keeps the caller's local state (an open dialog, the current
 * selection) across the redirect; the list itself lives in the Pinia Colada
 * cache, so the caller invalidates rather than relying on the refreshed props.
 */
export function inertiaWrite(
    target: InertiaWriteTarget,
    data?: RequestPayload,
): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        let failure: InertiaWriteError | null = null;
        let succeeded = false;

        router.visit(target.url, {
            method: target.method.toLowerCase() as Method,
            data,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                succeeded = true;
            },
            onError: (errors: Record<string, string>) => {
                failure = new InertiaWriteError(
                    firstMessage(errors) ??
                        'The request could not be completed.',
                    errors,
                );
            },
            // Settling here rather than inside the callbacks above covers the
            // third outcome — a visit cancelled or superseded before either
            // fired — which would otherwise leave the promise pending forever
            // and a mutation stuck in `isLoading`.
            onFinish: () => {
                if (failure) {
                    reject(failure);

                    return;
                }

                if (succeeded) {
                    resolve();

                    return;
                }

                reject(new InertiaWriteError('The request did not complete.'));
            },
        });
    });
}
