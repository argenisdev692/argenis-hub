<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import BrandLogo from '@/common/brand/BrandLogo.vue';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/**
 * The card for every unauthenticated flow.
 *
 * It floats on the site-wide hero glow (painted by `body::before`) rather than
 * laying down a background of its own, so signing in looks like the same
 * product as the landing page.
 */
const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

defineSlots<{
    default: () => unknown;
}>();

const appName = computed(() => usePage().props.name);
</script>

<template>
    <div class="relative flex min-h-svh flex-col p-4 sm:p-6">
        <header class="flex items-center justify-between">
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
            >
                <Link :href="home()">
                    <ArrowLeft />
                    Back
                </Link>
            </Button>

            <ThemeToggle />
        </header>

        <main class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-sm">
                <div class="flex flex-col items-center gap-3 text-center">
                    <!--
                      The full wordmark, not the glyph in a tile: this is the
                      first screen of the product a returning user sees, and
                      the one place the brand is worth stating in full.
                    -->
                    <Link :href="home()" class="rounded-md">
                        <BrandLogo :alt="appName" class="h-9 sm:h-10" />
                    </Link>

                    <div class="space-y-1.5">
                        <h1
                            v-if="title"
                            class="text-xl font-semibold tracking-tight text-balance"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-pretty text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-8 rounded-2xl border border-glass-border bg-surface-glass p-6 shadow-soft backdrop-blur-md sm:p-8"
                >
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
