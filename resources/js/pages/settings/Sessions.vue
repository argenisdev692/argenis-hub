<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy, index, revokeOthers } from '@/routes/auth/sessions';

/**
 * FR-14 — the user's own active sessions, with per-session and bulk revocation.
 *
 * `sessions` is typed from the generated backend contract, so the snake_case
 * keys stay in sync with `AuthSessionData` automatically.
 */
type AuthSession = Modules.Auth.Application.DTOs.AuthSessionData;

defineProps<{
    sessions: AuthSession[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Active sessions',
                href: index(),
            },
        ],
    },
});

function formatWhen(value: string | null): string {
    if (value === null) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Active sessions" />

    <h1 class="sr-only">Active sessions</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Active sessions"
            description="Devices that are currently signed in to your account. Sign out anything you don't recognise."
        />

        <ul class="space-y-3" role="list">
            <li
                v-for="session in sessions"
                :key="session.uuid"
                class="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
            >
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-medium">
                            {{ session.user_agent ?? 'Unknown device' }}
                        </span>
                        <Badge v-if="session.is_current" variant="secondary">
                            This device
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ session.ip_address ?? 'Unknown IP' }} &middot; last active
                        {{ formatWhen(session.last_seen_at) }}
                    </p>
                </div>

                <Form
                    v-if="!session.is_current"
                    v-bind="destroy.form(session.uuid)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" size="sm" :disabled="processing">
                        Sign out
                    </Button>
                </Form>
            </li>
        </ul>

        <p v-if="sessions.length === 0" class="text-muted-foreground text-sm">
            No other active sessions.
        </p>

        <Form
            v-if="sessions.length > 1"
            v-bind="revokeOthers.form()"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <Button type="submit" variant="destructive" :disabled="processing">
                Sign out all other sessions
            </Button>
        </Form>
    </div>
</template>
