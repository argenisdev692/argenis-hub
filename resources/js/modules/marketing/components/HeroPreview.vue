<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { m, motionValue, useSpring } from 'motion-v';
import { computed, onBeforeUnmount, useTemplateRef } from 'vue';
import Sparkline from '@/common/charts/Sparkline.vue';
import { REVEAL_ITEM } from '@/lib/motion';
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

/**
 * Pointer-tracked tilt.
 *
 * Kept deliberately shallow. This frame is a full dashboard mock, not a
 * product shot — past about six degrees the text blocks inside it start to
 * shear and the whole thing reads as broken rather than dimensional.
 */
const MAX_TILT_DEGREES = 6;

/**
 * Loose and slightly over-damped so the frame drifts after the pointer instead
 * of tracking it exactly. Snapping to the cursor is what makes a tilt feel
 * cheap; the lag is the effect.
 */
const TILT_SPRING = { stiffness: 150, damping: 20 } as const;

const frame = useTemplateRef<HTMLDivElement>('frame');

const pointerRotateX = motionValue(0);
const pointerRotateY = motionValue(0);

const rotateX = useSpring(pointerRotateX, TILT_SPRING);
const rotateY = useSpring(pointerRotateY, TILT_SPRING);

/**
 * The same two exclusions `CursorOrb` makes, for the same reasons: a tilt
 * chasing a finger is meaningless on touch, and incidental motion is exactly
 * what WCAG 2.2 SC 2.3.3 asks us to drop.
 *
 * `MotionConfig`'s `reduced-motion` setting does not cover this on its own —
 * it governs animations, and this is a style binding driven by pointer input,
 * which the library has no reason to treat as one. The guard has to be here.
 */
const isCoarsePointer = useMediaQuery('(pointer: coarse)');
const prefersReducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');

const isTiltEnabled = computed(
    () => !isCoarsePointer.value && !prefersReducedMotion.value,
);

/**
 * Listeners are bound only while the tilt is enabled, rather than attached and
 * early-returning — on a touch device this component ends up with no pointer
 * handlers at all.
 */
const tiltListeners = computed(() =>
    isTiltEnabled.value
        ? { pointermove: handlePointerMove, pointerleave: resetTilt }
        : {},
);

let animationFrame = 0;
let pointerX = 0;
let pointerY = 0;

/**
 * Writes are coalesced to one per animation frame, and the element's rect is
 * read inside that frame rather than per event.
 *
 * `pointermove` fires far more often than the display refreshes, and
 * `getBoundingClientRect()` forces a layout — doing that per event is what
 * turns a decorative tilt into jank on the one screen the visitor sees first.
 */
function paintTilt(): void {
    animationFrame = 0;

    const element = frame.value;

    if (element === null) {
        return;
    }

    const rect = element.getBoundingClientRect();

    if (rect.width === 0 || rect.height === 0) {
        return;
    }

    const offsetX = (pointerX - rect.left) / rect.width - 0.5;
    const offsetY = (pointerY - rect.top) / rect.height - 0.5;

    /**
     * Y drives rotateX and X drives rotateY — a pointer moving right tips the
     * frame around its vertical axis, not its horizontal one. The X rotation is
     * negated so the edge nearest the pointer comes toward the viewer.
     */
    pointerRotateX.set(-offsetY * MAX_TILT_DEGREES * 2);
    pointerRotateY.set(offsetX * MAX_TILT_DEGREES * 2);
}

function handlePointerMove(event: PointerEvent): void {
    pointerX = event.clientX;
    pointerY = event.clientY;

    if (animationFrame === 0) {
        animationFrame = requestAnimationFrame(paintTilt);
    }
}

function resetTilt(): void {
    pointerRotateX.set(0);
    pointerRotateY.set(0);
}

onBeforeUnmount(() => {
    if (animationFrame !== 0) {
        cancelAnimationFrame(animationFrame);
    }
});
</script>

<template>
    <m.div
        ref="frame"
        :class="cn('relative mx-auto w-full max-w-5xl', className)"
        aria-hidden="true"
        :variants="REVEAL_ITEM"
        initial="hidden"
        while-in-view="visible"
        :style="{ perspective: '1200px' }"
        v-on="tiltListeners"
    >
        <m.div
            class="overflow-hidden rounded-2xl border border-glass-border bg-surface-glass-strong shadow-lifted backdrop-blur-md"
            :style="{ rotateX, rotateY }"
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
        </m.div>

        <!-- Fades the frame into the page instead of ending on a hard edge.
             Outside the tilted element on purpose: the gradient masks the join
             with the page background, so it has to stay flat against the page
             rather than rotating away from it. -->
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-background"
        />
    </m.div>
</template>
