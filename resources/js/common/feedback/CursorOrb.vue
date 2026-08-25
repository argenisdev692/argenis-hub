<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

/**
 * A soft accent glow that trails the pointer.
 *
 * Decorative only: `aria-hidden`, `pointer-events: none`, and pinned behind
 * content at `z-index: -1` so it reads through the app's translucent surfaces
 * instead of washing over text. Styling lives in `app.css` (`.cursor-orb`);
 * this component only feeds it coordinates.
 *
 * It stays invisible until the pointer actually moves, so it never flashes at
 * the 0,0 corner on load, and it never attaches a listener at all on coarse
 * pointers or under a reduced-motion preference — a glow chasing a finger is
 * meaningless on touch, and incidental motion is exactly what WCAG 2.2 SC
 * 2.3.3 asks us to drop.
 *
 * Writes are coalesced to one per animation frame: `pointermove` can fire far
 * more often than the display refreshes, and every extra write is a style
 * recalculation nobody sees.
 */
const orb = useTemplateRef<HTMLDivElement>('orb');
const isActive = ref(false);

/**
 * Teleported to `<body>` and rendered only after mount.
 *
 * Both matter. The app shell paints `backdrop-filter` on its glass surfaces,
 * and a filtered ancestor becomes the containing block for `position: fixed`
 * descendants — mounted inline, the orb would be trapped inside the sidebar
 * inset instead of tracking the viewport. Skipping SSR keeps a purely
 * decorative node out of the server payload and avoids a teleport target that
 * does not exist yet.
 */
const isMounted = ref(false);

let frame = 0;
let pointerX = 0;
let pointerY = 0;

function paint(): void {
    frame = 0;

    const element = orb.value;

    if (element === null) {
        return;
    }

    element.style.setProperty('--cursor-x', `${pointerX}px`);
    element.style.setProperty('--cursor-y', `${pointerY}px`);

    isActive.value = true;
}

function handlePointerMove(event: PointerEvent): void {
    pointerX = event.clientX;
    pointerY = event.clientY;

    if (frame === 0) {
        frame = requestAnimationFrame(paint);
    }
}

onMounted(() => {
    isMounted.value = true;

    const isCoarse = window.matchMedia('(pointer: coarse)').matches;
    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (isCoarse || prefersReducedMotion) {
        return;
    }

    window.addEventListener('pointermove', handlePointerMove, {
        passive: true,
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('pointermove', handlePointerMove);

    if (frame !== 0) {
        cancelAnimationFrame(frame);
        frame = 0;
    }
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div
            ref="orb"
            :class="['cursor-orb', isActive && 'cursor-orb--active']"
            aria-hidden="true"
        />
    </Teleport>
</template>
