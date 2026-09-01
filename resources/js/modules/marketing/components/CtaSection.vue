<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { m } from 'motion-v';
import { Button } from '@/components/ui/button';
import type { MotionVariants } from '@/lib/motion';
import { BRAND_TRANSITION, MOTION_STAGGER, REVEAL_ITEM } from '@/lib/motion';

const emit = defineEmits<{
    signIn: [];
}>();

/**
 * The one place on the page that does not use `staggerContainer()`, because it
 * needs the container to animate as well as sequence: the card arrives as a
 * single object, then its contents cascade inside it. The shared factory
 * deliberately leaves the parent inert, so this composes the two here rather
 * than adding a second parameter nothing else would pass.
 *
 * `delayChildren` holds the copy until the panel has essentially finished
 * arriving — text sliding around inside a surface that is itself still moving
 * is what makes a CTA feel unsettled at exactly the moment it should feel
 * decisive.
 */
const ctaPanel: MotionVariants = {
    hidden: { opacity: 0, scale: 0.98 },
    visible: {
        opacity: 1,
        scale: 1,
        transition: {
            ...BRAND_TRANSITION,
            staggerChildren: MOTION_STAGGER,
            delayChildren: 0.15,
        },
    },
};
</script>

<template>
    <section class="px-4 py-20 sm:px-6 lg:py-28" aria-labelledby="cta-title">
        <m.div
            class="relative isolate mx-auto max-w-4xl overflow-hidden rounded-3xl border border-glass-border bg-surface-glass px-6 py-16 text-center backdrop-blur-md sm:px-12"
            :variants="ctaPanel"
            initial="hidden"
            while-in-view="visible"
        >
            <div
                class="pointer-events-none absolute -top-24 left-1/2 -z-10 h-96 w-96 -translate-x-1/2 ambient-blob-animated ambient-blob-purple"
                aria-hidden="true"
            />

            <m.h2
                id="cta-title"
                class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                :variants="REVEAL_ITEM"
            >
                One hub, five modules, one login
            </m.h2>

            <m.p
                class="mx-auto mt-4 max-w-xl text-pretty text-muted-foreground sm:text-lg"
                :variants="REVEAL_ITEM"
            >
                Content, campaigns, ATS, appointments and invoicing already
                share the same records, permissions and activity trail. Sign in
                and pick up where you left off.
            </m.p>

            <m.div
                class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                :variants="REVEAL_ITEM"
            >
                <Button
                    variant="gold"
                    size="lg"
                    class="w-full sm:w-auto"
                    @click="emit('signIn')"
                >
                    Sign in
                    <ArrowRight />
                </Button>
            </m.div>
        </m.div>
    </section>
</template>
