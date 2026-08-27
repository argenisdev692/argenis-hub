<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * Animated gradient backdrop for the public landing page.
 *
 * Three soft, slowly drifting brand-hue pools on a fixed, full-viewport layer
 * behind the page — the "ambient blobs" look every AI/SaaS site ships in 2026,
 * trimmed to what one marketing page needs. Decorative only: `aria-hidden`,
 * `pointer-events: none`, pinned at `z-index: -1`.
 *
 * Scoped to `/` on purpose. `body::before` already paints a damped mesh across
 * the whole product; the app screens are data-dense and seen all day, so they
 * keep that restrained version. This layer is louder and lives here only.
 *
 * All of its look — sizes, hues, blend mode, the drift keyframes, the
 * reduced-motion and mobile freezes — lives in `app.css` (`.landing-backdrop`).
 * Teleported to <body> and mounted client-side only, same reasoning as
 * `CursorOrb.vue`: keeps a purely decorative node off the SSR payload and out
 * of any `backdrop-filter` containing block.
 */
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div class="landing-backdrop" aria-hidden="true">
            <div class="landing-backdrop__blob landing-backdrop__blob--a" />
            <div class="landing-backdrop__blob landing-backdrop__blob--b" />
            <div class="landing-backdrop__blob landing-backdrop__blob--c" />
        </div>
    </Teleport>
</template>
