<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Menu } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { m } from 'motion-v';
import { computed } from 'vue';
import BrandLogo from '@/common/brand/BrandLogo.vue';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { BRAND_TRANSITION } from '@/lib/motion';
import { dashboard } from '@/routes';
import { NAV_LINKS } from '../content';

const { appName, isAuthenticated } = defineProps<{
    appName: string;
    isAuthenticated: boolean;
}>();

const emit = defineEmits<{
    signIn: [];
}>();

const { y } = useWindowScroll();

/**
 * The bar starts transparent so the hero glow runs edge to edge, and only
 * frosts once content is scrolling underneath it.
 */
const isDetached = computed(() => y.value > 12);

/**
 * Explicit heights, because motion animates the value and `h-16` cannot be
 * tweened. In `rem`, not pixels, and that matters: `h-16` resolves to `4rem`,
 * so a hardcoded `64` would only agree with it at a 16px root font size. Any
 * visitor who has raised their browser's default text size would watch the bar
 * snap shorter the moment motion took over — a regression aimed squarely at
 * the people least able to absorb it.
 */
const NAV_HEIGHT_BASE = '4rem';
const NAV_HEIGHT_COMPACT = '3.5rem';

const navHeight = computed(() =>
    isDetached.value ? NAV_HEIGHT_COMPACT : NAV_HEIGHT_BASE,
);

/**
 * A settle, not a slide-down.
 *
 * The obvious treatment is to drop the whole bar in from `y: -100%`, and it is
 * the wrong call here: the bar holds the only sign-in affordance above the
 * fold, so a bundle that loads slowly would leave the page's primary action
 * off-screen. 16px and a fade read as deliberate without ever hiding it.
 */
const NAV_ENTRANCE = {
    initial: { opacity: 0, y: -16 },
    animate: { opacity: 1, y: 0 },
} as const;
</script>

<template>
    <m.header
        :class="[
            'fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,box-shadow] duration-300 ease-brand',
            isDetached
                ? 'border-b border-glass-border bg-surface-glass backdrop-blur-md'
                : 'border-b border-transparent',
        ]"
        :initial="NAV_ENTRANCE.initial"
        :animate="NAV_ENTRANCE.animate"
        :transition="BRAND_TRANSITION"
    >
        <m.nav
            class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            aria-label="Main"
            :animate="{ height: navHeight }"
            :transition="BRAND_TRANSITION"
        >
            <!--
              The wordmark already reads the brand name, so it replaces the
              glyph-plus-text pair outright rather than sitting next to a second
              copy of it. `alt` is what names the link.
            -->
            <Link href="/" class="flex items-center rounded-md">
                <!-- Deferred: the bar itself is mid-entrance (NAV_ENTRANCE), and
                     two fades running together read as one. -->
                <BrandLogo
                    :alt="appName"
                    class="h-8 brand-logo-deferred sm:h-9"
                />
            </Link>

            <ul class="hidden items-center gap-1 md:flex">
                <li v-for="link in NAV_LINKS" :key="link.href">
                    <a
                        :href="link.href"
                        class="rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </a>
                </li>
            </ul>

            <div class="flex items-center gap-1.5">
                <ThemeToggle />

                <template v-if="isAuthenticated">
                    <Button as-child variant="gold" size="sm">
                        <Link :href="dashboard()">
                            Dashboard
                            <ArrowRight />
                        </Link>
                    </Button>
                </template>

                <template v-else>
                    <Button
                        variant="gold"
                        size="sm"
                        class="hidden sm:inline-flex"
                        @click="emit('signIn')"
                    >
                        Sign in
                    </Button>
                </template>

                <Sheet>
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="md:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu />
                        </Button>
                    </SheetTrigger>

                    <SheetContent side="right" class="w-72">
                        <SheetHeader>
                            <SheetTitle>{{ appName }}</SheetTitle>
                        </SheetHeader>

                        <nav class="grid gap-1 px-4" aria-label="Mobile">
                            <a
                                v-for="link in NAV_LINKS"
                                :key="link.href"
                                :href="link.href"
                                class="rounded-md px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                {{ link.label }}
                            </a>
                        </nav>

                        <div
                            v-if="!isAuthenticated"
                            class="mt-auto grid gap-2 p-4"
                        >
                            <Button variant="gold" @click="emit('signIn')">
                                Sign in
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </m.nav>
    </m.header>
</template>
