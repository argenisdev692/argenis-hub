import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * Global Inertia v3 error surfaces (`httpException`, `networkError`).
 *
 * Per-visit `onHttpException` / `onNetworkError` callbacks suppress these
 * globals by returning `false`; these handlers only run for visits that do
 * not handle the failure themselves. The `httpException` toast is additive —
 * nothing is returned, so the default error-page navigation still runs.
 */
export function initializeInertiaErrorHandling(): void {
    if (typeof document === 'undefined') {
        return;
    }

    router.on('networkError', () => {
        toast.error('Connection lost', {
            description: 'Check your connection and try again.',
        });
    });

    router.on('httpException', (event) => {
        const status = event.detail.response.status;

        if (status >= 500) {
            toast.error(`Server error (${status})`, {
                description:
                    'Something went wrong on our side. Please try again.',
            });

            return;
        }

        const clientError = CLIENT_ERRORS[status];

        if (clientError) {
            toast.error(clientError.title, {
                description: clientError.description,
            });
        }
    });
}

/**
 * The 4xx answers a form submit can get that are not a validation bag. Without
 * these a rejected upload (expired session, oversized file, missing permission)
 * looked exactly like a click that did nothing.
 */
const CLIENT_ERRORS: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Not allowed (403)',
        description: 'Your account does not have permission for this action.',
    },
    413: {
        title: 'File too large (413)',
        description: 'The upload exceeds the server limit.',
    },
    419: {
        title: 'Session expired (419)',
        description: 'Reload the page and try again.',
    },
    429: {
        title: 'Too many requests (429)',
        description: 'Wait a moment before trying again.',
    },
};
