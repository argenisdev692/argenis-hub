<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * The site-wide film-grain overlay.
 *
 * Decorative only: `aria-hidden`, `pointer-events: none`, pinned behind content
 * at `z-index: -1` so it reads through the app's translucent surfaces. All of
 * its look — the SVG noise texture, the blend mode, the opacity token — lives
 * in `app.css` (`.noise-grain`); this component only puts the node on the page.
 *
 * Teleported to `<body>` and rendered only after mount, for the same two
 * reasons as `CursorOrb.vue`: the app shell paints `backdrop-filter` on its
 * glass surfaces, and a filtered ancestor becomes the containing block for a
 * `position: fixed` descendant — mounted inline it would be clipped to the
 * sidebar inset instead of covering the viewport. Skipping SSR keeps a purely
 * decorative node out of the server payload and off a teleport target that
 * does not exist yet.
 *
 * Unlike the cursor orb it holds no state and binds no listeners: the texture
 * is static, so there is nothing to tear down and nothing for a reduced-motion
 * preference to switch off (the CSS drops it for print and that is all it
 * needs).
 */
const isMounted = ref(false);

onMounted(() => {
    isMounted.value = true;
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <div class="noise-grain" aria-hidden="true" />
    </Teleport>
</template>
