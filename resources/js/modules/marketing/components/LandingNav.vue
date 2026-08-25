<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Menu } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { computed } from 'vue';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, register } from '@/routes';
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
</script>

<template>
    <header
        :class="[
            'fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,box-shadow] duration-300 ease-brand',
            isDetached
                ? 'border-b border-glass-border bg-surface-glass backdrop-blur-md'
                : 'border-b border-transparent',
        ]"
    >
        <nav
            class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6"
            aria-label="Main"
        >
            <Link
                href="/"
                class="flex items-center gap-2.5 rounded-md font-semibold tracking-tight"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-brand-gradient"
                >
                    <AppLogoIcon class="size-4 fill-current text-white" />
                </span>
                <span>{{ appName }}</span>
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
                    <Button as-child size="sm">
                        <Link :href="dashboard()">
                            Dashboard
                            <ArrowRight />
                        </Link>
                    </Button>
                </template>

                <template v-else>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="hidden sm:inline-flex"
                        @click="emit('signIn')"
                    >
                        Sign in
                    </Button>
                    <Button as-child size="sm" class="hidden sm:inline-flex">
                        <Link :href="register()">Start free</Link>
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
                            <Button variant="outline" @click="emit('signIn')">
                                Sign in
                            </Button>
                            <Button as-child>
                                <Link :href="register()">Start free</Link>
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </nav>
    </header>
</template>
