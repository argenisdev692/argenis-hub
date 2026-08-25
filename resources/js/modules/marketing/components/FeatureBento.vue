<script setup lang="ts">
import { FEATURES } from '../content';
import FeatureCard from './FeatureCard.vue';
import LandingSection from './LandingSection.vue';
</script>

<template>
    <LandingSection
        id="platform"
        eyebrow="The modules"
        title="Five modules, one source of truth"
        lede="AI content, lead campaigns, ATS, appointments and invoicing — each a first-class part of the hub, not an add-on bolted to a contacts list. They share the same records, the same permissions and the same audit trail."
    >
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <FeatureCard
                v-for="feature in FEATURES"
                :key="feature.id"
                :feature="feature"
            />
        </div>
    </LandingSection>
</template>
