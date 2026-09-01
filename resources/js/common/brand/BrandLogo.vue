<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { cn } from '@/lib/utils';

/**
 * The company logotype, as shipped in `public/img`.
 *
 * Two assets, one component, because the wordmark is not theme-neutral:
 * `Logo.webp` is drawn for light grounds and `Logo-white.webp` for dark ones,
 * and dropping either one onto the wrong surface makes the brand disappear.
 * `Mark.webp` is the square glyph — it carries its own colour, so it needs no
 * pair and is what the sidebar falls back to when it collapses to icon width.
 *
 * The swap is done in CSS rather than by reading the theme store, for three
 * reasons: `common/` is not allowed to import from `modules/` (where the store
 * lives), a JS-driven `src` would flash the wrong asset on the first SSR paint,
 * and a class-toggled pair changes over in the same frame as everything else
 * when the theme toggle is hit. The cost is that both files are fetched — 34 KB
 * once, then cached — which is the right trade for a mark that sits in the app
 * shell on every screen.
 *
 * Motion and elevation come from the `brand-logo` utility in `app.css`; see the
 * comment there for why it settles once instead of looping.
 */

type BrandLogoAsset = 'wordmark' | 'mark';

/**
 * `auto` follows the theme. `on-dark` pins the white wordmark, for a mark that
 * sits on a surface which is dark in BOTH themes — a gradient panel, a coloured
 * header band — where following the theme would be exactly wrong.
 */
type BrandLogoTone = 'auto' | 'on-dark';

const {
    asset = 'wordmark',
    tone = 'auto',
    alt = '',
} = defineProps<{
    asset?: BrandLogoAsset;
    tone?: BrandLogoTone;
    /**
     * Leave empty when the logo sits inside a link that is already named —
     * either by adjacent visible text or by the link's own `aria-label`.
     * Repeating the brand name there makes a screen reader announce it twice.
     */
    alt?: string;
}>();

defineOptions({
    inheritAttrs: false,
});

/**
 * `class` is pulled out of the spread and folded through `cn()` instead.
 * Spreading it alongside a separate `:class` binding makes Vue concatenate the
 * two lists, which is precisely what defeats tailwind-merge: a consumer's
 * `h-7` would land next to the component's own sizing rather than replacing it.
 */
const attrs = useAttrs();

const passthroughAttrs = computed(() =>
    Object.fromEntries(
        Object.entries(attrs).filter(([key]) => key !== 'class'),
    ),
);

/**
 * Narrowed rather than cast: Vue passes `$attrs.class` through unnormalised, so
 * it arrives as `unknown`, and every call site in this app hands it a string.
 */
const consumerClass = computed(() =>
    typeof attrs.class === 'string' ? attrs.class : undefined,
);

/** Intrinsic pixel sizes, passed through so the box is reserved before decode. */
const DIMENSIONS: Readonly<Record<BrandLogoAsset, { w: number; h: number }>> = {
    wordmark: { w: 600, h: 149 },
    mark: { w: 158, h: 157 },
};

/**
 * The image(s) to render, in DOM order.
 *
 * A theme-following wordmark is two elements whose visibility CSS decides;
 * everything else is one. Only ever one of them is displayed, so both carry the
 * same `alt` — a `display: none` image is out of the accessibility tree, and
 * duplicating the name would be wrong in exactly the case where it is read.
 */
const layers = computed<ReadonlyArray<{ src: string; visibility: string }>>(
    () => {
        if (asset === 'mark') {
            return [{ src: '/img/Mark.webp', visibility: '' }];
        }

        if (tone === 'on-dark') {
            return [{ src: '/img/Logo-white.webp', visibility: '' }];
        }

        return [
            { src: '/img/Logo.webp', visibility: 'dark:hidden' },
            { src: '/img/Logo-white.webp', visibility: 'hidden dark:block' },
        ];
    },
);

const dimensions = computed(() => DIMENSIONS[asset]);
</script>

<template>
    <span class="inline-flex shrink-0 items-center">
        <img
            v-for="layer in layers"
            :key="layer.src"
            :src="layer.src"
            :alt="alt"
            :width="dimensions.w"
            :height="dimensions.h"
            decoding="async"
            draggable="false"
            v-bind="passthroughAttrs"
            :class="cn('brand-logo w-auto', layer.visibility, consumerClass)"
        />
    </span>
</template>
