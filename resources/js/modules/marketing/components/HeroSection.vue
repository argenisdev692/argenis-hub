<script setup lang="ts">
import { ArrowRight, Sparkles } from '@lucide/vue';
import { m } from 'motion-v';
import { Button } from '@/components/ui/button';
import { MOTION_STAGGER, REVEAL_ITEM, staggerContainer } from '@/lib/motion';
import { TRUST_ICON, TRUST_POINTS } from '../content';
import HeroPreview from './HeroPreview.vue';

const { appName } = defineProps<{
    appName: string;
}>();

const emit = defineEmits<{
    signIn: [];
}>();

/**
 * The hero animates on mount rather than on scroll — it is already in view when
 * the page loads, so a `while-in-view` reveal would either fire instantly
 * (pointless) or never (if the observer settles after paint).
 *
 * `delayChildren` holds the cascade back a beat so it starts after the first
 * paint has settled instead of racing the font swap, which is what makes a
 * staggered heading look like it stutters on a cold load.
 */
const heroCascade = staggerContainer(MOTION_STAGGER * 1.5, 0.1);

/**
 * The trust list staggers faster and as its own group. Seven items on the
 * parent's rhythm would still be arriving long after the CTA is clickable —
 * the cascade should be finished before the user can act on it.
 */
const trustCascade = staggerContainer(MOTION_STAGGER * 0.5);
</script>

<template>
    <section
        class="relative isolate overflow-hidden px-4 pt-32 pb-20 sm:px-6 lg:pt-44 lg:pb-28"
        aria-labelledby="hero-title"
    >
        <!-- Atmospheric glows. Decorative only: the fixed hero-glow image is
             already behind the whole page, these add depth at section scale.
             Deliberately outside the cascade — they are background, and fading
             them in on the same rhythm as the copy draws the eye to the
             wrong thing. -->
        <div
            class="pointer-events-none absolute -top-32 left-1/2 -z-10 h-[42rem] w-[42rem] -translate-x-1/2 ambient-blob-purple"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -bottom-40 -left-32 -z-10 h-[32rem] w-[32rem] ambient-blob-cyan"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-32 -bottom-40 -z-10 h-[32rem] w-[32rem] ambient-blob-magenta"
            aria-hidden="true"
        />

        <m.div
            class="mx-auto flex max-w-3xl flex-col items-center text-center"
            :variants="heroCascade"
            initial="hidden"
            animate="visible"
        >
            <m.p
                class="inline-flex items-center gap-2 rounded-full border border-glass-border bg-surface-glass px-3.5 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur-sm"
                :variants="REVEAL_ITEM"
            >
                <Sparkles class="size-3.5 text-brand-cyan" aria-hidden="true" />
                AI content, campaigns, ATS, appointments and invoicing
            </m.p>

            <m.h1
                id="hero-title"
                class="mt-7 text-4xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl"
                :variants="REVEAL_ITEM"
            >
                Run the whole operation from
                <span
                    class="bg-brand-gradient-animated bg-clip-text text-transparent"
                    >{{ appName }}</span
                >
            </m.h1>

            <m.p
                class="mt-6 max-w-2xl text-lg text-pretty text-muted-foreground sm:text-xl"
                :variants="REVEAL_ITEM"
            >
                Generate the posts, run the lead campaigns, optimise the CV,
                take the bookings and send the invoice — five modules on one
                record set, with an audit trail behind every number.
            </m.p>

            <m.div
                class="mt-9 flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row"
                :variants="REVEAL_ITEM"
            >
                <Button
                    size="lg"
                    class="w-full sm:w-auto"
                    @click="emit('signIn')"
                >
                    Sign in
                    <ArrowRight />
                </Button>

                <Button
                    as-child
                    variant="outline"
                    size="lg"
                    class="w-full sm:w-auto"
                >
                    <a href="#platform">See the modules</a>
                </Button>
            </m.div>

            <!-- Security posture as social proof: it is what this product
                 actually competes on, and it is verifiable. -->
            <m.ul
                class="mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-muted-foreground"
                :variants="trustCascade"
            >
                <m.li
                    v-for="point in TRUST_POINTS"
                    :key="point.id"
                    class="inline-flex items-center gap-2"
                    :variants="REVEAL_ITEM"
                >
                    <component
                        :is="TRUST_ICON"
                        class="size-4 text-success"
                        aria-hidden="true"
                    />
                    {{ point.label }}
                </m.li>
            </m.ul>
        </m.div>

        <HeroPreview class="mt-16 lg:mt-20" />
    </section>
</template>
