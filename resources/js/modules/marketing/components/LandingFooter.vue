<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login, register } from '@/routes';
import { NAV_LINKS } from '../content';

const { appName } = defineProps<{
    appName: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="px-4 pb-12 sm:px-6">
        <div class="mx-auto max-w-6xl">
            <div class="h-px w-full rule-fade" />

            <div
                class="flex flex-col items-center justify-between gap-6 pt-10 sm:flex-row"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex size-7 items-center justify-center rounded-lg bg-brand-gradient"
                    >
                        <AppLogoIcon class="size-3.5 fill-current text-white" />
                    </span>
                    <span class="text-sm font-semibold tracking-tight">
                        {{ appName }}
                    </span>
                </div>

                <nav aria-label="Footer">
                    <ul
                        class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground"
                    >
                        <li v-for="link in NAV_LINKS" :key="link.href">
                            <a
                                :href="link.href"
                                class="rounded-md transition-colors hover:text-foreground"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                        <li>
                            <Link
                                :href="login()"
                                class="rounded-md transition-colors hover:text-foreground"
                            >
                                Sign in
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="register()"
                                class="rounded-md transition-colors hover:text-foreground"
                            >
                                Start free
                            </Link>
                        </li>
                    </ul>
                </nav>

                <p class="text-sm text-muted-foreground tabular-nums">
                    © {{ year }} {{ appName }}
                </p>
            </div>
        </div>
    </footer>
</template>
