/**
 * Minimal JSON client for `/data/admin/*` endpoints consumed by Pinia Colada
 * queries and mutations — the JSON CRUD surface a table talks to directly,
 * bypassing Inertia's `router` (which expects an Inertia response, not a
 * plain JSON one; see `FRONTEND/SKILL.md` §6 state-ownership rule).
 *
 * Laravel's session-based CSRF check reads the `X-XSRF-TOKEN` header against
 * the encrypted `XSRF-TOKEN` cookie it sets on every response. Inertia's own
 * XHR client attaches that header automatically; a bare `fetch()` does not,
 * so it is read from the cookie and attached here once instead of in every
 * mutation.
 */

export class HttpError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors?: Record<string, string[]>,
    ) {
        super(message);
    }
}

function readXsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function toHttpError(res: Response): Promise<HttpError> {
    const body = (await res.json().catch(() => null)) as {
        message?: string;
        errors?: Record<string, string[]>;
    } | null;

    return new HttpError(
        body?.message ?? 'Something went wrong. Please try again.',
        res.status,
        body?.errors,
    );
}

export type JsonMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export async function httpJson<TResponse>(
    url: string,
    options: { method?: JsonMethod; body?: unknown } = {},
): Promise<TResponse> {
    const { method = 'GET', body } = options;

    const headers: Record<string, string> = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (method !== 'GET') {
        const token = readXsrfToken();

        if (token) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }

    const res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (!res.ok) {
        throw await toHttpError(res);
    }

    if (res.status === 204) {
        return undefined as TResponse;
    }

    return res.json() as Promise<TResponse>;
}
