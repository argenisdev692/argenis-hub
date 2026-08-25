<script setup lang="ts">
import { computed, useId } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Layout primitive for every landing section: consistent rhythm, an optional
 * eyebrow/title/lede header, and the shared max-width.
 *
 * It exists so the sections carry content, not spacing decisions — changing
 * the vertical rhythm of the page is one edit here.
 */
const {
    id,
    eyebrow,
    title,
    lede,
    align = 'center',
    class: className,
} = defineProps<{
    id?: string;
    eyebrow?: string;
    title?: string;
    lede?: string;
    align?: 'center' | 'start';
    class?: string;
}>();

defineSlots<{
    default: () => unknown;
}>();

const headingId = useId();

const hasHeader = computed(() => Boolean(eyebrow || title || lede));

/**
 * A section is only a labelled landmark when it actually has a heading to
 * point at; otherwise `aria-labelledby` would reference nothing.
 */
const labelledBy = computed(() => (title ? headingId : undefined));
</script>

<template>
    <section
        :id="id"
        :aria-labelledby="labelledBy"
        :class="cn('relative px-4 py-20 sm:px-6 lg:py-28', className)"
    >
        <div class="mx-auto w-full max-w-6xl">
            <header
                v-if="hasHeader"
                :class="
                    cn(
                        'mb-12 flex max-w-2xl flex-col gap-4 lg:mb-16',
                        align === 'center' && 'mx-auto text-center',
                    )
                "
            >
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>

                <h2
                    v-if="title"
                    :id="headingId"
                    class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                >
                    {{ title }}
                </h2>

                <p
                    v-if="lede"
                    class="text-base text-pretty text-muted-foreground sm:text-lg"
                >
                    {{ lede }}
                </p>
            </header>

            <slot />
        </div>
    </section>
</template>
