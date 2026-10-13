import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { watch } from 'vue';

/**
 * Calls `refresh` every `intervalMs` while `shouldPoll()` holds and the tab is
 * visible — a queued or rendering edit advances on the server, so its row must
 * too, but a finished history should cost nothing.
 *
 * Built on `useIntervalFn` rather than Pinia Colada's `autoRefetch`, which is a
 * separate plugin this project does not install. The timer is scoped to the
 * calling component and torn down with it.
 */
export function usePollWhile(
    shouldPoll: () => boolean,
    refresh: () => unknown,
    intervalMs = 4000,
): void {
    const visibility = useDocumentVisibility();
    const poll = useIntervalFn(() => void refresh(), intervalMs, {
        immediate: false,
    });

    watch(
        () => shouldPoll() && visibility.value === 'visible',
        (active) => {
            if (!active) {
                poll.pause();

                return;
            }

            // Fetch on the way in, not one interval later. `useIntervalFn` waits
            // its full interval before the first call, so without this the page
            // shows whatever it had for a whole tick after polling starts — which
            // is exactly the moment a user is watching: right after they approve
            // an AI cut review and the edit goes back on the queue.
            void refresh();
            poll.resume();
        },
        { immediate: true },
    );
}
