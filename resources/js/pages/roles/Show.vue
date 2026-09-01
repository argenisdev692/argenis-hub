<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatDateTime,
    suspensionLabel,
    suspensionVariant,
} from '@/modules/authorization/helpers/authorizationPresentation';
import type { RoleDetail } from '@/modules/authorization/types';
import { index } from '@/routes/roles';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

/**
 * Read-only detail for one role, rendered by `RoleController::show`.
 *
 * Editing stays on the index page's dialog: it needs the permission catalogue
 * (`availablePermissions`), which only `index()` shares, and keeping one form
 * means one place where the "sync, don't merge" semantics are explained.
 *
 * `show()` shares the role alone — no `protectedRoles` — so the system-role
 * marker lives on the index page, where the authoritative list is available.
 * Re-declaring `SystemRoles::PROTECTED` here would be a second copy of a domain
 * invariant.
 */
const { role } = defineProps<{ role: RoleDetail }>();

const permissionNames = computed(() =>
    role.permissions.map((permission) => permission.name),
);
</script>

<template>
    <Head :title="`Role · ${role.name}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-3">
            <Button variant="ghost" size="sm" class="w-fit" as-child>
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back to roles
                </Link>
            </Button>

            <header class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ role.name }}
                </h1>

                <Badge :variant="suspensionVariant(role.deleted_at)">
                    {{ suspensionLabel(role.deleted_at) }}
                </Badge>
            </header>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>
                    Authorization guard and lifecycle timestamps.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-muted-foreground">Guard</dt>
                        <dd class="font-mono text-sm">{{ role.guard_name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-muted-foreground">Created</dt>
                        <dd class="text-sm">
                            {{ formatDateTime(role.created_at) ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="role.deleted_at" class="flex flex-col gap-1">
                        <dt class="text-sm text-muted-foreground">Suspended</dt>
                        <dd class="text-sm">
                            {{ formatDateTime(role.deleted_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>
                    Permissions ({{ permissionNames.length }})
                </CardTitle>
                <CardDescription>
                    Everything a holder of this role may do. Edit the set from
                    the roles list.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="permissionNames.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    This role grants nothing yet.
                </p>

                <ul v-else class="flex flex-wrap gap-1.5">
                    <li v-for="name in permissionNames" :key="name">
                        <Badge variant="secondary" class="font-mono">
                            {{ name }}
                        </Badge>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
