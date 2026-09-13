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
        (active) => (active ? poll.resume() : poll.pause()),
        { immediate: true },
    );
}
