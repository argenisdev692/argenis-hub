<script setup lang="ts">
import { PencilIcon } from '@lucide/vue';
import { m } from 'motion-v';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { REVEAL_ITEM } from '@/lib/motion';

/**
 * One titled region of the company record, with its edit affordance.
 *
 * The guard lives here rather than at each call site so no section can ship
 * with a visible Edit button by omission. It hides the control only — the route
 * behind it is independently guarded by `permission:UPDATE_COMPANY_DATA`.
 *
 * Reveals as a `REVEAL_ITEM`, so a parent stagger container sequences the cards
 * without either side knowing the index.
 */
const {
    title,
    description,
    editLabel = 'Edit',
    permission = 'UPDATE_COMPANY_DATA',
} = defineProps<{
    title: string;
    description?: string;
    /** Accessible name for the action, e.g. "Edit address". */
    editLabel?: string;
    permission?: string;
}>();

defineEmits<{ edit: [] }>();

defineSlots<{
    /** The section body — usually a `CompanyDetailList`. */
    default: () => unknown;
}>();
</script>

<template>
    <m.div :variants="REVEAL_ITEM">
        <Card class="h-full">
            <CardHeader>
                <CardTitle>{{ title }}</CardTitle>
                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>

                <CardAction>
                    <PermissionGuard :permission="permission">
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="editLabel"
                            @click="$emit('edit')"
                        >
                            <PencilIcon class="size-4" aria-hidden="true" />
                            <span class="sr-only sm:not-sr-only">Edit</span>
                        </Button>
                    </PermissionGuard>
                </CardAction>
            </CardHeader>

            <CardContent>
                <slot />
            </CardContent>
        </Card>
    </m.div>
</template>
