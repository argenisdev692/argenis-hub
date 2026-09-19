<script setup lang="ts">
import { ShieldCheckIcon, StarIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { safeExternalUrl } from '@/lib/utils';
import type { LeadDecisor } from '../types';

const { decisor, canManage } = defineProps<{
    decisor: LeadDecisor;
    canManage: boolean;
}>();

const emit = defineEmits<{
    object: [uuid: string];
    makePrimary: [uuid: string];
}>();
</script>

<template>
    <Card>
        <CardContent class="flex flex-col gap-2 p-4">
            <div class="flex items-center gap-2">
                <StarIcon
                    v-if="decisor.is_primary"
                    class="size-4 shrink-0 fill-current text-primary"
                    aria-label="Primary contact"
                />
                <p class="flex-1 text-sm font-medium">
                    {{ decisor.full_name ?? 'Anonymized' }}
                </p>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ decisor.role_title ?? '—' }} · {{ decisor.role_category ?? '—' }}
            </p>

            <p
                v-if="decisor.published_email"
                class="text-xs text-muted-foreground"
            >
                {{ decisor.published_email }}
                <span v-if="decisor.email_kind">({{ decisor.email_kind }})</span>
            </p>

            <a
                v-if="safeExternalUrl(decisor.public_profile_url) !== null"
                :href="safeExternalUrl(decisor.public_profile_url) ?? undefined"
                target="_blank"
                rel="noopener noreferrer"
                class="text-xs text-primary underline"
            >
                Linked profile (opens manually — never fetched by the system)
            </a>

            <p
                v-if="decisor.evidence_excerpt"
                class="text-xs text-muted-foreground"
            >
                “{{ decisor.evidence_excerpt }}”
            </p>

            <div
                v-if="canManage"
                class="flex gap-2 pt-1"
            >
                <Button
                    v-if="!decisor.is_primary"
                    variant="outline"
                    size="sm"
                    @click="emit('makePrimary', decisor.uuid)"
                >
                    <StarIcon class="size-3" aria-hidden="true" />
                    Primary
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="emit('object', decisor.uuid)"
                >
                    <ShieldCheckIcon class="size-3" aria-hidden="true" />
                    Object
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
