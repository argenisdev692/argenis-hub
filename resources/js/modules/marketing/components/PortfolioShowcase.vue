<script setup lang="ts">
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    ExternalLinkIcon,
    ImageIcon,
    PlayIcon,
    XIcon,
} from '@lucide/vue';
import { onKeyStroke } from '@vueuse/core';
import { m } from 'motion-v';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { MOTION_STAGGER, REVEAL_ITEM, staggerContainer } from '@/lib/motion';
import type { PublicPortfolio } from '@/modules/portfolios/types';
import { usePublicPortfolios } from '../composables/usePublicPortfolios';
import LandingSection from './LandingSection.vue';

/**
 * "Selected work" for the landing page, fed by the public REST endpoint
 * (`GET /api/public/portfolios`). The same feed powers standalone sites, so the
 * card here is deliberately self-contained — cover, meta, blurb, stack, link.
 *
 * Clicking any media opens a lightbox that walks the project's full ordered
 * media set (cover → gallery images → showcase video). The order is fixed by the
 * backend's `sort_order`; the lightbox's prev/next controls and the ←/→ keys are
 * how a visitor moves through it. The video plays inline in that same viewer.
 *
 * The section removes itself when there is nothing published: a public page
 * should never render an empty "our work" block.
 */
const { portfolios, isPending } = usePublicPortfolios();

const cardCascade = staggerContainer(MOTION_STAGGER);

const hasContent = computed(
    () => isPending.value || portfolios.value.length > 0,
);

const skeletonKeys = ['a', 'b', 'c'] as const;

type LightboxItem =
    | { kind: 'image'; src: string }
    | { kind: 'video'; src: string; poster: string | null };

/** Cover first, then the ordered gallery, then the showcase video (if any). */
function mediaItems(project: PublicPortfolio): LightboxItem[] {
    const items: LightboxItem[] = [];

    if (project.cover_url) {
        items.push({ kind: 'image', src: project.cover_url });
    }

    for (const src of project.gallery) {
        items.push({ kind: 'image', src });
    }

    if (project.video_url) {
        items.push({
            kind: 'video',
            src: project.video_url,
            poster: project.cover_url,
        });
    }

    return items;
}

function hasMedia(project: PublicPortfolio): boolean {
    return (
        Boolean(project.cover_url) ||
        project.gallery.length > 0 ||
        Boolean(project.video_url)
    );
}

/** Lightbox index of the first gallery image — one past the cover, if present. */
function galleryOffset(project: PublicPortfolio): number {
    return project.cover_url ? 1 : 0;
}

const activeProject = ref<PublicPortfolio | null>(null);
const activeIndex = ref(0);

const activeItems = computed<LightboxItem[]>(() =>
    activeProject.value ? mediaItems(activeProject.value) : [],
);

const activeItem = computed<LightboxItem | null>(
    () => activeItems.value[activeIndex.value] ?? null,
);

const lightboxOpen = computed(() => activeProject.value !== null);

function openLightbox(project: PublicPortfolio, index: number): void {
    const items = mediaItems(project);

    if (items.length === 0) {
        return;
    }

    activeProject.value = project;
    activeIndex.value = Math.min(Math.max(index, 0), items.length - 1);
}

function closeLightbox(): void {
    activeProject.value = null;
}

function step(delta: number): void {
    const total = activeItems.value.length;

    if (total === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + delta + total) % total;
}

/**
 * Ignore the arrow keys while the focus is inside a `<video>` — there they
 * belong to the native scrub controls, not to slide navigation.
 */
function navKey(event: KeyboardEvent, delta: number): void {
    if (
        !lightboxOpen.value ||
        (event.target as HTMLElement | null)?.tagName === 'VIDEO'
    ) {
        return;
    }

    event.preventDefault();
    step(delta);
}

onKeyStroke('ArrowRight', (event) => navKey(event, 1));
onKeyStroke('ArrowLeft', (event) => navKey(event, -1));
</script>

<template>
    <LandingSection
        v-if="hasContent"
        id="work"
        eyebrow="Selected work"
        title="Projects we have shipped"
        lede="A slice of the portfolio — pulled live from the same public API that feeds our client showcase sites."
    >
        <div v-if="isPending" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="key in skeletonKeys"
                :key="key"
                class="flex flex-col gap-4 rounded-2xl border border-glass-border bg-surface-glass p-4 backdrop-blur-sm"
            >
                <Skeleton class="aspect-video w-full rounded-xl" />
                <Skeleton class="h-5 w-2/3" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-4/5" />
            </div>
        </div>

        <m.ul
            v-else
            class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
            :variants="cardCascade"
            initial="hidden"
            while-in-view="visible"
        >
            <m.li
                v-for="project in portfolios"
                :key="project.uuid"
                class="group flex flex-col overflow-hidden rounded-2xl border border-glass-border bg-surface-glass backdrop-blur-sm"
                :variants="REVEAL_ITEM"
            >
                <div
                    class="relative aspect-video w-full overflow-hidden bg-primary/8"
                >
                    <button
                        v-if="hasMedia(project)"
                        type="button"
                        class="block size-full focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        :aria-label="`Open the ${project.title} media viewer`"
                        @click="openLightbox(project, 0)"
                    >
                        <img
                            v-if="project.cover_url"
                            :src="project.cover_url"
                            :alt="`${project.title} cover`"
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                        />
                        <span
                            v-else
                            class="flex size-full items-center justify-center text-muted-foreground"
                        >
                            <ImageIcon class="size-8" aria-hidden="true" />
                        </span>

                        <span
                            v-if="project.video_url"
                            class="absolute inset-0 grid place-items-center"
                        >
                            <span
                                class="rounded-full border border-border bg-background/80 p-3 backdrop-blur-sm transition-transform duration-300 group-hover:scale-110"
                            >
                                <PlayIcon
                                    class="size-5 text-foreground"
                                    aria-hidden="true"
                                />
                            </span>
                        </span>
                    </button>

                    <span
                        v-else
                        class="flex size-full items-center justify-center text-muted-foreground"
                    >
                        <ImageIcon class="size-8" aria-hidden="true" />
                    </span>
                </div>

                <div class="flex flex-1 flex-col gap-3 p-5">
                    <div class="flex flex-col gap-1">
                        <h3 class="font-semibold tracking-tight">
                            {{ project.title }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ project.client_name }} ·
                            {{ project.project_type }}
                        </p>
                    </div>

                    <p
                        v-if="project.description"
                        class="line-clamp-3 text-sm text-pretty text-muted-foreground"
                    >
                        {{ project.description }}
                    </p>

                    <ul
                        v-if="project.gallery.length"
                        class="flex flex-wrap gap-1.5"
                        :aria-label="`${project.title} gallery`"
                    >
                        <li
                            v-for="(src, galleryIndex) in project.gallery.slice(
                                0,
                                4,
                            )"
                            :key="src"
                        >
                            <button
                                type="button"
                                class="size-12 overflow-hidden rounded-md border border-border transition-opacity hover:opacity-80 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                :aria-label="`Open gallery image ${galleryIndex + 1}`"
                                @click="
                                    openLightbox(
                                        project,
                                        galleryOffset(project) + galleryIndex,
                                    )
                                "
                            >
                                <img
                                    :src="src"
                                    alt=""
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                            </button>
                        </li>
                        <li
                            v-if="project.gallery.length > 4"
                            class="grid size-12 place-items-center rounded-md border border-border text-xs text-muted-foreground"
                        >
                            +{{ project.gallery.length - 4 }}
                        </li>
                    </ul>

                    <ul
                        v-if="project.tech_stack.length"
                        class="mt-auto flex flex-wrap gap-1.5 pt-1"
                    >
                        <li
                            v-for="tech in project.tech_stack.slice(0, 5)"
                            :key="tech"
                            class="rounded-full bg-primary/12 px-2 py-0.5 font-mono text-xs text-primary"
                        >
                            {{ tech }}
                        </li>
                    </ul>

                    <a
                        v-if="project.live_url"
                        :href="project.live_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Visit site
                        <ExternalLinkIcon class="size-4" aria-hidden="true" />
                    </a>
                </div>
            </m.li>
        </m.ul>
    </LandingSection>

    <Dialog
        :open="lightboxOpen"
        @update:open="(value) => !value && closeLightbox()"
    >
        <DialogContent
            :show-close-button="false"
            class="max-w-[min(94vw,1100px)] border-0 bg-transparent p-0 shadow-none sm:max-w-[min(94vw,1100px)]"
        >
            <DialogTitle class="sr-only">
                {{ activeProject?.title ?? 'Portfolio' }} media viewer
            </DialogTitle>
            <DialogDescription class="sr-only">
                Use the left and right arrow keys, or the on-screen controls, to
                move between the cover, gallery images and video.
            </DialogDescription>

            <div v-if="activeItem" class="flex flex-col gap-3">
                <div
                    class="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-black/95"
                >
                    <video
                        v-if="activeItem.kind === 'video'"
                        :key="activeItem.src"
                        :src="activeItem.src"
                        :poster="activeItem.poster ?? undefined"
                        controls
                        playsinline
                        preload="metadata"
                        class="max-h-full max-w-full"
                    />
                    <img
                        v-else
                        :key="activeItem.src"
                        :src="activeItem.src"
                        :alt="`${activeProject?.title} media ${activeIndex + 1}`"
                        class="max-h-full max-w-full object-contain"
                    />

                    <button
                        v-if="activeItems.length > 1"
                        type="button"
                        class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full border border-border bg-background/80 p-2 text-foreground backdrop-blur-sm transition-colors hover:bg-background focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        aria-label="Previous item"
                        @click="step(-1)"
                    >
                        <ChevronLeftIcon class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        v-if="activeItems.length > 1"
                        type="button"
                        class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full border border-border bg-background/80 p-2 text-foreground backdrop-blur-sm transition-colors hover:bg-background focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        aria-label="Next item"
                        @click="step(1)"
                    >
                        <ChevronRightIcon class="size-5" aria-hidden="true" />
                    </button>
                </div>

                <div
                    class="flex items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 py-2"
                >
                    <span class="text-sm text-muted-foreground tabular-nums">
                        {{ activeIndex + 1 }} / {{ activeItems.length }}
                        <span v-if="activeItem.kind === 'video'"> · Video</span>
                    </span>
                    <button
                        type="button"
                        class="rounded-md p-1 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        aria-label="Close media viewer"
                        @click="closeLightbox"
                    >
                        <XIcon class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <ul
                    v-if="activeItems.length > 1"
                    class="flex flex-wrap justify-center gap-1.5"
                >
                    <li
                        v-for="(item, thumbIndex) in activeItems"
                        :key="item.src"
                    >
                        <button
                            type="button"
                            class="size-14 overflow-hidden rounded-md border-2 transition-opacity focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            :class="
                                thumbIndex === activeIndex
                                    ? 'border-primary'
                                    : 'border-transparent opacity-60 hover:opacity-100'
                            "
                            :aria-label="`Go to item ${thumbIndex + 1}`"
                            :aria-current="
                                thumbIndex === activeIndex ? 'true' : undefined
                            "
                            @click="activeIndex = thumbIndex"
                        >
                            <span
                                v-if="item.kind === 'video'"
                                class="grid size-full place-items-center bg-black/95"
                            >
                                <PlayIcon
                                    class="size-4 text-primary-foreground"
                                    aria-hidden="true"
                                />
                            </span>
                            <img
                                v-else
                                :src="item.src"
                                alt=""
                                class="size-full object-cover"
                            />
                        </button>
                    </li>
                </ul>
            </div>
        </DialogContent>
    </Dialog>
</template>
