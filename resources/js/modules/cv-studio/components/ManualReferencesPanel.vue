<script setup lang="ts">
import { ClipboardPasteIcon, LinkIcon } from '@lucide/vue';
import { useId } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Paginator } from '@/common/table';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useReferences } from '../composables/useReferences';
import { formatDate } from '../helpers/studioPresentation';
import type { StudioReference } from '../types';

/**
 * Link-only signals: postings a source may not be fetched from. The user
 * opens each in their own browser and pastes the text back.
 */
const emit = defineEmits<{
    paste: [reference: StudioReference];
}>();

const { references, meta, page, isPending, isLoading } = useReferences();
const headingId = useId();
</script>

<template>
    <section class="flex flex-col gap-3" :aria-labelledby="headingId">
        <p :id="headingId" class="text-sm text-muted-foreground">
            Link-only signals — open each in your own browser, then paste the
            text you read to score it like any other posting.
        </p>

        <div v-if="isPending" class="flex flex-col gap-2" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-16 rounded-xl" />
        </div>

        <EmptyState
            v-else-if="references.length === 0"
            :icon="LinkIcon"
            title="Nothing to open manually"
            description="Postings from sources that cannot be fetched land here for you to paste."
        />

        <template v-else>
            <ul
                class="flex flex-col gap-2 transition-opacity"
                :class="{ 'opacity-60': isLoading }"
            >
                <li
                    v-for="reference in references"
                    :key="reference.uuid"
                    class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{ reference.title }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ reference.employer_name ?? 'Unknown employer' }}
                            <template v-if="reference.created_at">
                                · {{ formatDate(reference.created_at) }}
                            </template>
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ reference.canonical_url }}
                        </p>
                    </div>

                    <PermissionGuard permission="CREATE_STUDIO_POSTINGS">
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="emit('paste', reference)"
                        >
                            <ClipboardPasteIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                            Paste posting text
                        </Button>
                    </PermissionGuard>
                </li>
            </ul>

            <Paginator
                v-if="meta.last_page > 1"
                v-model:page="page"
                :meta="meta"
                :disabled="isLoading"
                label="links"
                class="rounded-xl border border-border bg-card"
            />
        </template>
    </section>
</template>
