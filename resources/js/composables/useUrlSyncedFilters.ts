import type { Ref } from 'vue';
import { watch } from 'vue';

type Primitive = string | number | boolean;

type FilterValue = Primitive | Primitive[] | null;

type Options<T> = {
    /** The pristine filter state. Its keys define what is read/written, and
     *  each value's runtime type drives how the query param is coerced back.
     *  Array defaults sync as repeated `key[]` params (backend convention). */
    defaults: T;
    /** Keys to leave out of the URL entirely (e.g. `per_page`). */
    exclude?: readonly (keyof T & string)[];
};

/**
 * Two-way binding between a filters ref and the URL query string.
 *
 * - On setup: every query param whose key exists in `defaults` is read back,
 *   coerced to that default's type and written into `filters`, so a reloaded or
 *   shared link restores search / date range / page / sort.
 * - On change: params that differ from their default are written via
 *   `history.replaceState` — no Inertia visit, no server round-trip, no extra
 *   history entry. Params equal to their default are dropped so the URL stays
 *   short. Query params the caller does not own are left untouched.
 *
 * Module-agnostic: it only needs the shape of `defaults`. Call it once per
 * index page, right after the list composable that creates `filters`.
 */
export function useUrlSyncedFilters<T extends Record<string, FilterValue>>(
    filters: Ref<T>,
    options: Options<T>,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const { defaults, exclude = [] } = options;
    const excluded = new Set<string>(exclude);
    const keys = Object.keys(defaults) as (keyof T & string)[];

    function coerce(key: keyof T & string, raw: string): Primitive {
        const fallback = defaults[key];

        if (typeof fallback === 'number') {
            const parsed = Number(raw);

            return Number.isFinite(parsed) ? parsed : fallback;
        }

        if (typeof fallback === 'boolean') {
            return raw === 'true' || raw === '1';
        }

        return raw;
    }

    // ---- hydrate from the current URL ---------------------------------------
    const incoming = new URLSearchParams(window.location.search);
    const hydrated: Record<string, FilterValue> = { ...filters.value };

    for (const key of keys) {
        if (excluded.has(key)) {
            continue;
        }

        const fallback = defaults[key];

        if (Array.isArray(fallback)) {
            const raw = incoming.getAll(`${key}[]`);

            if (raw.length > 0) {
                hydrated[key] = raw;
            }

            continue;
        }

        const raw = incoming.get(key);

        if (raw === null) {
            continue;
        }

        hydrated[key] = coerce(key, raw);
    }

    filters.value = hydrated as T;

    // ---- persist on change ------------------------------------------------------
    watch(
        filters,
        (current) => {
            const params = new URLSearchParams(window.location.search);

            for (const key of keys) {
                if (excluded.has(key)) {
                    continue;
                }

                const value = current[key];
                const fallback = defaults[key];

                params.delete(key);
                params.delete(`${key}[]`);

                if (Array.isArray(fallback)) {
                    const list = Array.isArray(value) ? value : [];
                    const pristine =
                        list.length === 0 ||
                        (list.length === fallback.length &&
                            list.every((entry) => fallback.includes(entry)));

                    if (!pristine) {
                        for (const entry of list) {
                            params.append(`${key}[]`, String(entry));
                        }
                    }

                    continue;
                }

                if (value === fallback || value === null || value === '') {
                    continue;
                }

                params.set(key, String(value));
            }

            const query = params.toString();
            const url = query
                ? `${window.location.pathname}?${query}`
                : window.location.pathname;

            window.history.replaceState(window.history.state, '', url);
        },
        { deep: true },
    );
}
