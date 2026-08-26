<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { CompanyDetail } from '../types';

/**
 * The read-only half of a company section.
 *
 * A `<dl>` rather than a table: these are label/value pairs about one record,
 * not rows of comparable things, and a screen reader announces the pairing for
 * free. Missing values render an em dash instead of collapsing, so the shape of
 * the record — and what is still unfilled — stays visible.
 */
const { details, columns = 2 } = defineProps<{
    details: readonly CompanyDetail[];
    /** Columns from the `sm` breakpoint up. Below it, always one. */
    columns?: 1 | 2;
}>();
</script>

<template>
    <dl
        :class="
            cn(
                'grid gap-x-6 gap-y-4',
                columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-1',
            )
        "
    >
        <div
            v-for="detail in details"
            :key="detail.label"
            :class="
                detail.multiline && columns === 2 ? 'sm:col-span-2' : undefined
            "
        >
            <dt
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ detail.label }}
            </dt>

            <dd class="mt-1 text-sm break-words">
                <a
                    v-if="detail.value && detail.href"
                    :href="detail.href"
                    class="rounded-sm underline decoration-border underline-offset-4 transition-colors outline-none hover:decoration-current focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    rel="noopener noreferrer"
                >
                    {{ detail.value }}
                </a>

                <span
                    v-else-if="detail.value"
                    :class="
                        detail.multiline ? 'whitespace-pre-line' : undefined
                    "
                >
                    {{ detail.value }}
                </span>

                <span v-else class="text-muted-foreground" aria-label="Not set">
                    —
                </span>
            </dd>
        </div>
    </dl>
</template>
