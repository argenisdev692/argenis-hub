import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/clients/admin';
import type { Client, ClientPage } from '../types';

export type ClientOption = {
    uuid: string;
    label: string;
};

/**
 * Active clients as `<Select>` options, for every screen that links a record to
 * a client (the product catalog, the invoice form).
 *
 * Owned by the Clients module rather than duplicated per consumer: a page that
 * needs this imports it and hands the result down as props, so the dialogs stay
 * dumb and no module reaches sideways into another module's routes.
 *
 * Deliberately unpaginated and capped at 200: this backs a picker, not a table.
 * A single request keeps the select instant, and a solo-dev CRM that outgrows
 * 200 active clients wants a searchable async field instead of a bigger cap.
 */
export function useClientOptions() {
    const { data, ...query } = useQuery<ClientPage>({
        key: () => ['client-options'],
        query: () =>
            httpJson<ClientPage>(
                index.url({
                    query: { status: 'active', per_page: 200, sort_field: 'client_name', sort_order: 1 },
                }),
            ),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    const clientOptions = computed<ClientOption[]>(() =>
        (data.value?.data ?? []).map((client: Client) => ({
            uuid: client.uuid,
            label: client.client_name,
        })),
    );

    return { ...query, clientOptions };
}
