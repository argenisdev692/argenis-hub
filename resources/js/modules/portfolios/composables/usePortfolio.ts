import { useQuery } from '@pinia/colada';
import { httpJson } from '@/lib/http';
import { show } from '@/routes/portfolios/admin';
import type { Portfolio } from '../types';

/**
 * One portfolio in full — cover, gallery and all — behind `portfolios/Show`.
 *
 * Keyed under `['portfolios', 'detail', uuid]`, inside the `['portfolios']`
 * prefix every list mutation invalidates, so an edit saved from the detail
 * page never leaves a stale row behind. No polling: a portfolio never changes
 * on its own the way a processing video edit does.
 */
export function usePortfolio(uuid: () => string) {
    return useQuery<Portfolio>({
        key: () => ['portfolios', 'detail', uuid()],
        query: () => httpJson<Portfolio>(show.url(uuid())),
        staleTime: 1000 * 30,
        gcTime: 1000 * 60 * 5,
    });
}
