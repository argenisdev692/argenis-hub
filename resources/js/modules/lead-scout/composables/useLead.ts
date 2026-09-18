import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { show } from '@/routes/lead-scout/leads';
import type { LeadDetail } from '../types';

/** The key every LeadScout mutation invalidates alongside the list. */
export const LEAD_KEY = (uuid: string): string[] => ['lead-scout', 'lead', uuid];

/**
 * One bandeja detail graph — company, score with evidence, decisors,
 * ranked channels, outreaches. Server state, never mirrored.
 */
export function useLead(uuid: string) {
    const { data, ...query } = useQuery<LeadDetail>({
        key: () => LEAD_KEY(uuid),
        query: () => httpJson<LeadDetail>(show(uuid).url),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
    });

    const lead = computed(() => data.value ?? null);

    return { ...query, data, lead };
}
