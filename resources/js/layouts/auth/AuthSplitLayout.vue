<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { TRUST_ICON, TRUST_POINTS } from '@/modules/marketing/content';
import { home } from '@/routes';

/**
 * Two-pane variant of the auth layout: brand panel on the left, form on the
 * right. Not wired up by default (`AuthLayout` points at `AuthSimpleLayout`) —
 * swap the import there to use it.
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
    <div class="relative grid min-h-svh lg:grid-cols-2">
        <!-- Brand panel. Hidden below `lg`, where the single-column form wins. -->
        <div
            class="relative isolate hidden flex-col justify-between overflow-hidden border-r border-glass-border p-10 lg:flex"
        >
            <div
                class="pointer-events-none absolute -top-40 -left-20 -z-10 h-[36rem] w-[36rem] ambient-blob-purple"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-24 -bottom-40 -z-10 h-[32rem] w-[32rem] ambient-blob-cyan"
                aria-hidden="true"
            />

            <Link
                :href="home()"
                class="flex w-fit items-center gap-2.5 font-semibold tracking-tight"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-brand-gradient"
                >
                    <AppLogoIcon class="size-4 fill-current text-white" />
                </span>
                {{ appName }}
            </Link>

            <div class="max-w-md space-y-6">
                <p class="text-2xl font-semibold tracking-tight text-balance">
                    Vacancies, clients, invoices and content — one hub that
                    keeps them in step.
                </p>

                <ul class="space-y-3 text-sm text-muted-foreground">
                    <li
                        v-for="point in TRUST_POINTS"
                        :key="point.id"
                        class="flex items-center gap-2.5"
                    >
                        <component
                            :is="TRUST_ICON"
                            class="size-4 text-success"
                            aria-hidden="true"
                        />
                        {{ point.label }}
                    </li>
                </ul>
            </div>
        </div>

        <div class="relative flex flex-col p-4 sm:p-6">
            <div class="flex justify-end">
                <ThemeToggle />
            </div>

            <main class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-sm space-y-8">
                    <div class="space-y-1.5 text-center">
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

                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
