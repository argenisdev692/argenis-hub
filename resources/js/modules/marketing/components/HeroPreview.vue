<script setup lang="ts">
import Sparkline from '@/common/charts/Sparkline.vue';
import { cn } from '@/lib/utils';

/**
 * A stylised frame of the product, sitting under the hero.
 *
 * Entirely decorative — it is `aria-hidden`, carries no real figures, and is
 * built from tokens rather than a screenshot so it stays sharp, weighs nothing
 * and inverts with the theme instead of going stale.
 */
const { class: className } = defineProps<{
    class?: string;
}>();

const TILES = [
    { id: 'revenue', label: 'Revenue', width: 'w-16', tone: 'var(--chart-1)' },
    {
        id: 'pipeline',
        label: 'Pipeline',
        width: 'w-12',
        tone: 'var(--chart-2)',
    },
    { id: 'hires', label: 'Hires', width: 'w-10', tone: 'var(--chart-3)' },
] as const;

const TREND = [12, 18, 15, 24, 21, 32, 28, 41, 38, 52, 48, 61] as const;
</script>

<template>
    <div
        :class="cn('relative mx-auto w-full max-w-5xl', className)"
        aria-hidden="true"
    >
        <div
            class="overflow-hidden rounded-2xl border border-glass-border bg-surface-glass-strong shadow-lifted backdrop-blur-md"
        >
            <!-- Window chrome -->
            <div
                class="flex items-center gap-2 border-b border-glass-border px-4 py-3"
            >
                <span class="size-2.5 rounded-full bg-destructive/60" />
                <span class="size-2.5 rounded-full bg-warning/60" />
                <span class="size-2.5 rounded-full bg-success/60" />
                <span class="ml-3 h-5 w-40 rounded-md bg-muted sm:w-64" />
            </div>

            <div class="flex">
                <!-- Sidebar rail -->
                <div
                    class="hidden w-48 shrink-0 flex-col gap-2 border-r border-glass-border p-4 sm:flex"
                >
                    <span class="h-7 rounded-md bg-primary/15" />
                    <span
                        v-for="row in 5"
                        :key="row"
                        class="h-6 rounded-md bg-muted"
                        :style="{ opacity: 1 - row * 0.12 }"
                    />
                </div>

                <div class="flex-1 space-y-4 p-4 sm:p-6">
                    <!-- KPI row -->
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            v-for="tile in TILES"
                            :key="tile.id"
                            class="rounded-xl border border-glass-border bg-surface-glass p-3"
                        >
                            <span
                                class="block h-2 w-10 rounded-full bg-muted-foreground/30"
                            />
                            <span
                                :class="
                                    cn(
                                        'mt-2 block h-4 rounded-md bg-foreground/70',
                                        tile.width,
                                    )
                                "
                            />
                            <Sparkline
                                :values="TREND"
                                :stroke="tile.tone"
                                class="mt-3 h-6"
                            />
                        </div>
                    </div>

                    <!-- Chart panel -->
                    <div
                        class="rounded-xl border border-glass-border bg-surface-glass p-4"
                    >
                        <span
                            class="block h-2.5 w-24 rounded-full bg-muted-foreground/30"
                        />
                        <Sparkline
                            :values="TREND"
                            stroke="var(--chart-1)"
                            class="mt-4 h-24"
                        />
                    </div>

                    <!-- Table rows -->
                    <div class="space-y-2">
                        <div
                            v-for="row in 3"
                            :key="row"
                            class="flex items-center gap-3 rounded-lg border border-glass-border bg-surface-glass px-3 py-2.5"
                        >
                            <span class="size-6 rounded-full bg-primary/20" />
                            <span class="h-2.5 flex-1 rounded-full bg-muted" />
                            <span
                                class="hidden h-2.5 w-16 rounded-full bg-muted sm:block"
                            />
                            <span class="h-5 w-14 rounded-full bg-success/20" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fades the frame into the page instead of ending on a hard edge. -->
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-background"
        />
    </div>
</template>
