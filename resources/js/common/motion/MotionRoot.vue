<script setup lang="ts">
import { domAnimation, LazyMotion, MotionConfig } from 'motion-v';
import { REVEAL_VIEWPORT } from '@/lib/motion';

/**
 * Provides the motion system's defaults to everything beneath it.
 *
 * Renders no element of its own — both wrappers are context providers, so this
 * can sit around a layout's slot without disturbing the grid around it.
 *
 * `reduced-motion="user"` is the important setting. `app.css` already clamps
 * CSS animation and transition duration under `prefers-reduced-motion`, but
 * that rule cannot touch motion-v: it animates through the Web Animations API
 * and inline styles, which the cascade never sees. Without this prop the app
 * would honour the preference for its CSS motion and quietly ignore it for the
 * JS-driven half — the worst of both, and a WCAG 2.2 SC 2.3.3 failure.
 *
 * `in-view-options` makes `REVEAL_VIEWPORT` the default for every
 * `while-in-view` in the subtree, so a section opts out deliberately rather
 * than re-declaring the same thresholds correctly by luck.
 *
 * `LazyMotion` with the `domAnimation` bundle is what keeps the cost bearable.
 * The full `motion.*` component statically pulls in every feature the library
 * has, including layout projection and drag — together the bulk of its weight,
 * and nothing this app animates needs either. Pairing this bundle with the `m.*`
 * components lets the bundler drop that code: on the landing page it is the
 * difference between roughly 40 kB and 15 kB gzipped. `strict` turns the
 * matching mistake — reaching for `motion.*` inside this tree, which silently
 * re-adds the full bundle — into a development-time error instead of a
 * regression nobody notices until the next build report.
 */
</script>

<template>
    <MotionConfig reduced-motion="user" :in-view-options="REVEAL_VIEWPORT">
        <LazyMotion :features="domAnimation" strict>
            <slot />
        </LazyMotion>
    </MotionConfig>
</template>
