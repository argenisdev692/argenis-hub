import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import type { PublicPortfolio } from '@/modules/portfolios/types';
import { index } from '@/routes/api/public/portfolios';

/**
 * The published portfolio showcase for the public landing page.
 *
 * `GET /api/public/portfolios` is unauthenticated, rate-limited and answered
 * from a 30-minute server cache, so the client only needs a light touch: a long
 * `staleTime` keeps it from refetching while a visitor scrolls, and the payload
 * is a plain ordered array (`list<PublicPortfolioData>`), not a paginator.
 */
export function usePublicPortfolios() {
    const { data, ...query } = useQuery<PublicPortfolio[]>({
        key: () => ['public-portfolios'],
        query: () => httpJson<PublicPortfolio[]>(index.url()),
        staleTime: 1000 * 60 * 10,
        gcTime: 1000 * 60 * 30,
    });

    const portfolios = computed<PublicPortfolio[]>(() => data.value ?? []);

    return { ...query, data, portfolios };
}
