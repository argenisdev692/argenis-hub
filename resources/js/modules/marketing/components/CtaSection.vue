<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

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
                Bring the whole operation into one place
            </h2>

            <p
                class="mx-auto mt-4 max-w-xl text-pretty text-muted-foreground sm:text-lg"
            >
                Free to start. No card, no sales call — create an account and
                import your first client in a few minutes.
            </p>

            <div
                class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <Button as-child size="lg" class="w-full sm:w-auto">
                    <Link :href="register()">
                        Create your account
                        <ArrowRight />
                    </Link>
                </Button>

                <Button
                    variant="ghost"
                    size="lg"
                    class="w-full sm:w-auto"
                    @click="emit('signIn')"
                >
                    I already have one
                </Button>
            </div>
        </div>
    </section>
</template>
