import { createInertiaApp } from '@inertiajs/vue3';
import { PiniaColada } from '@pinia/colada';
import { createPinia } from 'pinia';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // The `@inertiajs/vite` plugin injects `resolve`; everything else this app
    // needs on the Vue instance is registered here. `withApp` runs for both the
    // client mount and SSR, right before `app.mount()` — the signature is
    // positional (`(app, { ssr, page }) => void`) in @inertiajs/vue3 v3.7.
    withApp: (app) => {
        app.use(createPinia());
        app.use(PiniaColada, {
            // Per-query `staleTime` / `gcTime` still win; this is only the
            // floor for any query that forgets to set one.
            queryOptions: {
                staleTime: 1000 * 60 * 2,
            },
        });
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
