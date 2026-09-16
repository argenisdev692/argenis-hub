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
        }
    });
}
