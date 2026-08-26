<script setup lang="ts">
import type { CompanyLogos, LogoVariant } from '../types';

/**
 * The three brand marks, each previewed against the backdrop it was drawn for.
 *
 * `logo_white` gets the fixed `bg-hub-dark` surface rather than a theme-aware
 * one: a white mark on a light `--card` is an empty box, and an operator who
 * cannot see the asset cannot tell they uploaded the wrong file. The dark
 * surface is mode-invariant for the same reason — the preview has to show what
 * the asset looks like in use, not what the app's current theme is.
 */
const { logos } = defineProps<{ logos: CompanyLogos }>();

type LogoPreview = {
    key: LogoVariant;
    label: string;
    description: string;
    /** Backdrop the mark is designed to sit on. */
    surface: string;
};

const PREVIEWS: readonly LogoPreview[] = [
    {
        key: 'logo',
        label: 'Primary',
        description: 'Light backgrounds',
        surface: 'bg-muted',
    },
    {
        key: 'logo_white',
        label: 'Reversed',
        description: 'Dark backgrounds',
        surface: 'bg-hub-dark',
    },
    {
        key: 'mark',
        label: 'Mark',
        description: 'Favicon and avatars',
        surface: 'bg-muted',
    },
];
</script>

<template>
    <ul class="grid gap-4 sm:grid-cols-3">
        <li v-for="preview in PREVIEWS" :key="preview.key">
            <figure class="flex h-full flex-col gap-2">
                <div
                    :class="[
                        'flex h-24 items-center justify-center rounded-lg border border-border p-4',
                        preview.surface,
                    ]"
                >
                    <img
                        :src="logos[preview.key]"
                        :alt="`${preview.label} brand mark`"
                        class="max-h-full w-auto max-w-full object-contain"
                        loading="lazy"
                        decoding="async"
                    />
                </div>

                <figcaption class="text-sm">
                    <span class="font-medium">{{ preview.label }}</span>
                    <span class="block text-xs text-muted-foreground">
                        {{ preview.description }}
                    </span>
                </figcaption>
            </figure>
        </li>
    </ul>
</template>
