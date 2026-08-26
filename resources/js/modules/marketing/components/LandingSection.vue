<script setup lang="ts">
import { m } from 'motion-v';
import { computed, useId } from 'vue';
import { MOTION_STAGGER, REVEAL_ITEM, staggerContainer } from '@/lib/motion';
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

/**
 * Every section header reveals on the same rhythm because it reveals from the
 * same place. Putting the cascade on the primitive rather than in each section
 * is what stops the page drifting into four slightly different entrances.
 *
 * Viewport thresholds are not set here — `MotionRoot` supplies them, so a
 * section that needs different ones has to say so out loud.
 */
const headerCascade = staggerContainer(MOTION_STAGGER);
</script>

<template>
    <section
        :id="id"
        :aria-labelledby="labelledBy"
        :class="cn('relative px-4 py-20 sm:px-6 lg:py-28', className)"
    >
        <div class="mx-auto w-full max-w-6xl">
            <m.header
                v-if="hasHeader"
                :class="
                    cn(
                        'mb-12 flex max-w-2xl flex-col gap-4 lg:mb-16',
                        align === 'center' && 'mx-auto text-center',
                    )
                "
                :variants="headerCascade"
                initial="hidden"
                while-in-view="visible"
            >
                <m.p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                    :variants="REVEAL_ITEM"
                >
                    {{ eyebrow }}
                </m.p>

                <m.h2
                    v-if="title"
                    :id="headingId"
                    class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                    :variants="REVEAL_ITEM"
                >
                    {{ title }}
                </m.h2>

                <m.p
                    v-if="lede"
                    class="text-base text-pretty text-muted-foreground sm:text-lg"
                    :variants="REVEAL_ITEM"
                >
                    {{ lede }}
                </m.p>
            </m.header>

            <slot />
        </div>
    </section>
</template>
