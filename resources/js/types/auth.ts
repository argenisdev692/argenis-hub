export type User = {
    id: number;
    uuid: string;
    first_name: string;
    last_name: string | null;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /**
     * Permission names granted to the current user, shared by
     * `HandleInertiaRequests::share()`.
     *
     * Permissions, never roles: a role is a backend grouping whose membership
     * can change without the UI knowing, so a screen that hides itself behind
     * `roles.includes('admin')` drifts the moment a permission is reassigned.
     * Hiding UI is defence in depth — the route middleware stays authoritative.
     */
    permissions: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
