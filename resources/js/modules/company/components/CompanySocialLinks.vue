<script setup lang="ts">
import { computed } from 'vue';
import type { SocialChannel } from '@/common/brand/socialChannels';
import { SOCIAL_CHANNELS } from '@/common/brand/socialChannels';
import SocialIcon from '@/common/brand/SocialIcon.vue';
import { externalHref } from '../helpers/companyDetails';
import type { CompanyProfile } from '../types';

/**
 * The linked social profiles, in the shared display order.
 *
 * Only channels with a URL are rendered — the same rule the marketing footer
 * follows, so the settings screen shows exactly what the public site will.
 */
const { company } = defineProps<{ company: CompanyProfile }>();

type ResolvedSocialLink = {
    key: SocialChannel;
    label: string;
    href: string;
};

/**
 * `flatMap` rather than `map().filter()` so the result narrows to a non-null
 * `href` without a type assertion — `filter` cannot tell TypeScript that.
 */
const links = computed<ResolvedSocialLink[]>(() =>
    SOCIAL_CHANNELS.flatMap((channel) => {
        const href = externalHref(company[channel.field]);

        return href ? [{ key: channel.key, label: channel.label, href }] : [];
    }),
);
</script>

<template>
    <ul v-if="links.length > 0" class="flex flex-wrap gap-2">
        <li v-for="link in links" :key="link.key">
            <a
                :href="link.href"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-1.5 text-sm transition-colors outline-none hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <SocialIcon :channel="link.key" class="size-4" />
                {{ link.label }}
                <span class="sr-only">(opens in a new tab)</span>
            </a>
        </li>
    </ul>

    <p v-else class="text-sm text-muted-foreground">
        No social profiles linked yet.
    </p>
</template>
