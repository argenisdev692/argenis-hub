import { useQuery, useQueryCache } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { index } from '@/routes/cv-studio/profiles';
import type { StudioProfile } from '../types';

/** The profile list also carries each profile's ruleset (policy editor). */
export type StudioProfileWithRules = StudioProfile & {
    rules?: Record<string, unknown>;
};

export const STUDIO_PROFILES_KEY = ['studio-profiles'];

/**
 * The owner's search profiles — shared by the settings page and the
 * "Start run" control, so both read one cache entry.
 */
export function useProfiles() {
    const { data, ...query } = useQuery<{ data: StudioProfileWithRules[] }>({
        key: () => [...STUDIO_PROFILES_KEY],
        query: () =>
            httpJson<{ data: StudioProfileWithRules[] }>(toUrl(index())),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    return { ...query, profiles: computed(() => data.value?.data ?? []) };
}

/**
 * Profile writes go through Inertia forms (redirect-back), which never touch
 * the Pinia Colada cache — call this from their `onSuccess` so the list shows
 * the new profile / policy without waiting out `staleTime`.
 */
export function useInvalidateProfiles() {
    const queryCache = useQueryCache();

    return async (): Promise<void> => {
        await queryCache.invalidateQueries({ key: STUDIO_PROFILES_KEY });
    };
}
