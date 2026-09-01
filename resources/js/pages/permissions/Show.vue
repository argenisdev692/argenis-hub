<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
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
import type { PermissionDetail } from '@/modules/authorization/types';
import { index } from '@/routes/permissions';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Permissions', href: index() }],
    },
});

/**
 * Read-only detail for one permission, rendered by `PermissionController::show`.
 *
 * `EloquentPermissionRepository::findByUuid()` loads neither the `roles`
 * relation nor its count, so this page shows the record alone — "which roles
 * hold it" is answered by the `Held by` column on the index list, which does
 * eager-load it.
 */
const { permission } = defineProps<{ permission: PermissionDetail }>();
</script>

<template>
    <Head :title="`Permission · ${permission.name}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-3">
            <Button variant="ghost" size="sm" class="w-fit" as-child>
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back to permissions
                </Link>
            </Button>

            <header class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl font-semibold tracking-tight">
                    {{ permission.name }}
                </h1>

                <Badge :variant="suspensionVariant(permission.deleted_at)">
                    {{ suspensionLabel(permission.deleted_at) }}
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
                        <dd class="font-mono text-sm">
                            {{ permission.guard_name }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-sm text-muted-foreground">Created</dt>
                        <dd class="text-sm">
                            {{ formatDateTime(permission.created_at) ?? '—' }}
                        </dd>
                    </div>
                    <div
                        v-if="permission.deleted_at"
                        class="flex flex-col gap-1"
                    >
                        <dt class="text-sm text-muted-foreground">Suspended</dt>
                        <dd class="text-sm">
                            {{ formatDateTime(permission.deleted_at) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
