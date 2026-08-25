<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const emit = defineEmits<{
    signIn: [];
}>();
</script>

<template>
    <section class="px-4 py-20 sm:px-6 lg:py-28" aria-labelledby="cta-title">
        <div
            class="relative isolate mx-auto max-w-4xl overflow-hidden rounded-3xl border border-glass-border bg-surface-glass px-6 py-16 text-center backdrop-blur-md sm:px-12"
        >
            <div
                class="pointer-events-none absolute -top-24 left-1/2 -z-10 h-96 w-96 -translate-x-1/2 ambient-blob-purple"
                aria-hidden="true"
            />

            <h2
                id="cta-title"
                class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
            >
                One hub, five modules, one login
            </h2>

            <p
                class="mx-auto mt-4 max-w-xl text-pretty text-muted-foreground sm:text-lg"
            >
                Content, campaigns, ATS, appointments and invoicing already
                share the same records, permissions and activity trail. Sign in
                and pick up where you left off.
            </p>

            <div
                class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <Button
                    size="lg"
                    class="w-full sm:w-auto"
                    @click="emit('signIn')"
                >
                    Sign in
                    <ArrowRight />
                </Button>
            </div>
        </div>
    </section>
</template>
