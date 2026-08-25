<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login } from '@/routes';
import { NAV_LINKS, SOCIAL_LABELS, SOCIAL_ORDER } from '../content';
import type { CompanyContact, SocialKey } from '../types';
import SocialIcon from './SocialIcon.vue';

const { appName, company } = defineProps<{
    appName: string;
    company: CompanyContact;
}>();

const year = new Date().getFullYear();

/**
 * Only the channels that actually have a URL on the `company_data` row, in the
 * order the brand wants them read — so adding a link in the database is enough
 * to make it appear here.
 */
const socials = computed<readonly { key: SocialKey; url: string }[]>(() =>
    SOCIAL_ORDER.flatMap((key) => {
        const url = company.socials[key];

        return url ? [{ key, url }] : [];
    }),
);
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
                    </ul>
                </nav>

                <ul
                    v-if="socials.length || company.support_email"
                    class="flex items-center gap-1"
                >
                    <li v-for="social in socials" :key="social.key">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener noreferrer external"
                            :aria-label="`${appName} on ${SOCIAL_LABELS[social.key]}`"
                            class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <SocialIcon :channel="social.key" class="size-4" />
                        </a>
                    </li>

                    <li v-if="company.support_email">
                        <a
                            :href="`mailto:${company.support_email}`"
                            :aria-label="`Email ${appName} at ${company.support_email}`"
                            class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <Mail class="size-4" aria-hidden="true" />
                        </a>
                    </li>
                </ul>
            </div>

            <p
                class="pt-8 text-center text-sm text-muted-foreground tabular-nums sm:text-left"
            >
                © {{ year }} {{ appName }}
            </p>
        </div>
    </footer>
</template>
