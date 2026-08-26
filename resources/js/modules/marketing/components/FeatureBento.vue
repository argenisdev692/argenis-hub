<script setup lang="ts">
import { m } from 'motion-v';
import { staggerContainer } from '@/lib/motion';
import { FEATURES } from '../content';
import FeatureCard from './FeatureCard.vue';
import LandingSection from './LandingSection.vue';

/**
 * The grid sequences the cards; each `FeatureCard` carries its own variant, so
 * neither needs to know its index.
 *
 * `amount: 0.1` overrides the system default here because this grid is the
 * tallest block on the page — at the shared quarter-visible threshold the top
 * row would already be halfway up the viewport before anything moved.
 */
const cardCascade = staggerContainer();
</script>

<template>
    <LandingSection
        id="platform"
        eyebrow="The modules"
        title="Five modules, one source of truth"
        lede="AI content, lead campaigns, ATS, appointments and invoicing — each a first-class part of the hub, not an add-on bolted to a contacts list. They share the same records, the same permissions and the same audit trail."
    >
        <m.div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6"
            :variants="cardCascade"
            initial="hidden"
            while-in-view="visible"
            :in-view-options="{ once: true, amount: 0.1 }"
        >
            <FeatureCard
                v-for="feature in FEATURES"
                :key="feature.id"
                :feature="feature"
            />
        </m.div>
    </LandingSection>
</template>
