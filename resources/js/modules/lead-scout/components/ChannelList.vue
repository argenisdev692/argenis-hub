<script setup lang="ts">
import { ExternalLinkIcon, TriangleAlertIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { safeExternalUrl } from '@/lib/utils';
import type { LeadChannel } from '../types';

const { channels, recommendedUuid, canManage } = defineProps<{
    channels: LeadChannel[];
    recommendedUuid: string | null;
    canManage: boolean;
}>();

const emit = defineEmits<{
    markBroken: [uuid: string];
}>();

function channelLabel(type: string): string {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-for="channel in channels"
            :key="channel.uuid ?? channel.type"
            class="flex flex-col gap-1 rounded-lg border border-border p-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="channel.allowed ? 'default' : 'destructive'">
                    #{{ channel.rank }} {{ channelLabel(channel.type) }}
                </Badge>
                <Badge
                    v-if="channel.uuid === recommendedUuid"
                    variant="secondary"
                >
                    Recommended
                </Badge>
                <span
                    v-if="channel.audience"
                    class="text-xs text-muted-foreground"
                >
                    → {{ channel.audience }}
                </span>
            </div>

            <p
                v-if="!channel.allowed && channel.blocked_reason"
                class="text-xs text-muted-foreground"
            >
                Blocked: {{ channel.blocked_reason }}
            </p>

            <p
                v-if="channel.warning"
                class="flex items-start gap-1 text-xs text-muted-foreground"
            >
                <TriangleAlertIcon class="mt-0.5 size-3 shrink-0" aria-hidden="true" />
                {{ channel.warning }}
            </p>

            <div class="flex flex-wrap gap-2 pt-1">
                <Button
                    v-if="safeExternalUrl(channel.url) !== null"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <a
                        :href="safeExternalUrl(channel.url) ?? undefined"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLinkIcon class="size-3" aria-hidden="true" />
                        Open to send manually
                    </a>
                </Button>
                <Button
                    v-if="canManage && channel.uuid && channel.allowed"
                    variant="ghost"
                    size="sm"
                    @click="emit('markBroken', channel.uuid)"
                >
                    Report broken
                </Button>
            </div>
        </div>

        <p
            v-if="channels.length === 0"
            class="text-sm text-muted-foreground"
        >
            No permitted channel — manual review required.
        </p>
    </div>
</template>
