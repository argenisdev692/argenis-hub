<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { TRUST_ICON, TRUST_POINTS } from '../content';
import HeroPreview from './HeroPreview.vue';

const { appName } = defineProps<{
    appName: string;
}>();

const emit = defineEmits<{
    signIn: [];
}>();
</script>

<template>
    <section
        class="relative isolate overflow-hidden px-4 pt-32 pb-20 sm:px-6 lg:pt-44 lg:pb-28"
        aria-labelledby="hero-title"
    >
        <!-- Atmospheric glows. Decorative only: the fixed hero-glow image is
             already behind the whole page, these add depth at section scale. -->
        <div
            class="pointer-events-none absolute -top-32 left-1/2 -z-10 h-[42rem] w-[42rem] -translate-x-1/2 ambient-blob-purple"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -bottom-40 -left-32 -z-10 h-[32rem] w-[32rem] ambient-blob-cyan"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-32 -bottom-40 -z-10 h-[32rem] w-[32rem] ambient-blob-magenta"
            aria-hidden="true"
        />

        <div class="mx-auto flex max-w-3xl flex-col items-center text-center">
            <p
                class="inline-flex items-center gap-2 rounded-full border border-glass-border bg-surface-glass px-3.5 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur-sm"
            >
                <Sparkles class="size-3.5 text-brand-cyan" aria-hidden="true" />
                AI-assisted CRM, ATS and invoicing in one hub
            </p>

            <h1
                id="hero-title"
                class="mt-7 text-4xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl"
            >
                Run the whole business from
                <span class="bg-brand-gradient bg-clip-text text-transparent">{{
                    appName
                }}</span>
            </h1>

            <p
                class="mt-6 max-w-2xl text-lg text-pretty text-muted-foreground sm:text-xl"
            >
                Vacancies, candidates, clients, invoices and content — one
                system that keeps them in step, with an audit trail behind every
                number.
            </p>

            <div
                class="mt-9 flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row"
            >
                <Button as-child size="lg" class="w-full sm:w-auto">
                    <Link :href="register()">
                        Start free
                        <ArrowRight />
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="lg"
                    class="w-full sm:w-auto"
                    @click="emit('signIn')"
                >
                    Sign in
                </Button>
            </div>

            <!-- Security posture as social proof: it is what this product
                 actually competes on, and it is verifiable. -->
            <ul
                class="mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-muted-foreground"
            >
                <li
                    v-for="point in TRUST_POINTS"
                    :key="point.id"
                    class="inline-flex items-center gap-2"
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

        <HeroPreview class="mt-16 lg:mt-20" />
    </section>
</template>
