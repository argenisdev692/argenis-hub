<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CtaSection from '@/modules/marketing/components/CtaSection.vue';
import FeatureBento from '@/modules/marketing/components/FeatureBento.vue';
import HeroSection from '@/modules/marketing/components/HeroSection.vue';
import LandingFooter from '@/modules/marketing/components/LandingFooter.vue';
import LandingNav from '@/modules/marketing/components/LandingNav.vue';
import LoginDialog from '@/modules/marketing/components/LoginDialog.vue';
import MetricsStrip from '@/modules/marketing/components/MetricsStrip.vue';
import WorkflowSection from '@/modules/marketing/components/WorkflowSection.vue';

/**
 * Public landing page.
 *
 * The site-wide hero glow is painted by `body::before` (see `app.css`), so
 * this page adds section-scale glows only and never sets a background of its
 * own.
 */
const page = usePage();

const appName = computed(() => page.props.name);
const isAuthenticated = computed(() => Boolean(page.props.auth.user));

const loginOpen = ref(false);

function openLogin(): void {
    loginOpen.value = true;
}
</script>

<template>
    <Head :title="`${appName} — CRM, ATS and invoicing in one hub`">
        <meta
            name="description"
            content="Run vacancies, candidates, clients, invoices and content from a single hub, with an audit trail behind every number."
        />
    </Head>

    <div class="min-h-svh">
        <a
            href="#main"
            class="sr-only focus-visible:not-sr-only focus-visible:fixed focus-visible:top-4 focus-visible:left-4 focus-visible:z-[60] focus-visible:rounded-md focus-visible:bg-primary focus-visible:px-4 focus-visible:py-2 focus-visible:text-primary-foreground"
        >
            Skip to content
        </a>

        <LandingNav
            :app-name="appName"
            :is-authenticated="isAuthenticated"
            @sign-in="openLogin"
        />

        <main id="main">
            <HeroSection :app-name="appName" @sign-in="openLogin" />
            <FeatureBento />
            <MetricsStrip />
            <WorkflowSection />
            <CtaSection @sign-in="openLogin" />
        </main>

        <LandingFooter :app-name="appName" />

        <LoginDialog v-model:open="loginOpen" />
    </div>
</template>
