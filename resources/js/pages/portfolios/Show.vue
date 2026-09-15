<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    PencilIcon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { ConfirmModal } from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import PortfolioFormSheet from '@/modules/portfolios/components/PortfolioFormSheet.vue';
import { usePortfolio } from '@/modules/portfolios/composables/usePortfolio';
import { usePortfolioMutations } from '@/modules/portfolios/composables/usePortfolioMutations';
import { formatDate } from '@/modules/portfolios/helpers/portfolioPresentation';
import { index } from '@/routes/portfolios';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Portfolio', href: index() }],
    },
});

const { uuid } = defineProps<{ uuid: string }>();

const { data: portfolio, isLoading, error, refetch } = usePortfolio(() => uuid);
const { deletePortfolio, restorePortfolio } = usePortfolioMutations();

const title = computed(() => portfolio.value?.title ?? 'Portfolio');

const sheetOpen = ref(false);
const confirmDeleteOpen = ref(false);

async function confirmDelete(): Promise<void> {
    try {
        await deletePortfolio.mutateAsync(uuid);
    } catch {
        return;
    }

    confirmDeleteOpen.value = false;
    router.visit(index());
}

async function onRestore(): Promise<void> {
    await restorePortfolio.mutateAsync(uuid).catch(() => undefined);
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to portfolios"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <h1 class="flex-1 text-2xl font-semibold tracking-tight">
                {{ title }}
            </h1>

            <template v-if="portfolio">
                <PermissionGuard permission="UPDATE_PORTFOLIOS">
                    <Button
                        v-if="!portfolio.deleted_at"
                        variant="outline"
                        @click="sheetOpen = true"
                    >
                        <PencilIcon class="size-4" aria-hidden="true" />
                        Edit
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="RESTORE_PORTFOLIOS">
                    <Button
                        v-if="portfolio.deleted_at"
                        variant="outline"
                        :disabled="restorePortfolio.isLoading.value"
                        @click="onRestore"
                    >
                        <RotateCcwIcon class="size-4" aria-hidden="true" />
                        Restore
                    </Button>
                </PermissionGuard>

                <PermissionGuard permission="DELETE_PORTFOLIOS">
                    <Button
                        v-if="!portfolio.deleted_at"
                        variant="ghost"
                        size="icon"
                        aria-label="Delete portfolio"
                        @click="confirmDeleteOpen = true"
                    >
                        <Trash2Icon
                            class="size-4 text-destructive"
                            aria-hidden="true"
                        />
                    </Button>
                </PermissionGuard>
            </template>
        </div>

        <div v-if="isLoading && !portfolio" class="grid gap-4" aria-busy="true">
            <Skeleton class="h-48 w-full animate-pulse" />
            <Skeleton class="h-32 w-full animate-pulse" />
        </div>

        <EmptyState
            v-else-if="error || !portfolio"
            title="Could not load this portfolio"
            description="It may have been deleted, or the link is wrong."
        >
            <template #action>
                <Button variant="outline" @click="refetch()">Try again</Button>
            </template>
        </EmptyState>

        <template v-else>
            <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="portfolio.is_public ? 'default' : 'secondary'">
                    {{ portfolio.is_public ? 'Public' : 'Private' }}
                </Badge>
                <Badge v-if="portfolio.published_at" variant="secondary">
                    Published {{ formatDate(portfolio.published_at) }}
                </Badge>
                <Badge v-else variant="outline">Draft</Badge>
                <Badge v-if="portfolio.deleted_at" variant="destructive">
                    Deleted
                </Badge>
            </div>

            <Card v-if="portfolio.cover_url">
                <CardContent class="p-0">
                    <img
                        :src="portfolio.cover_url"
                        :alt="`Cover image for ${portfolio.title}`"
                        class="max-h-80 w-full rounded-xl object-cover"
                    />
                </CardContent>
            </Card>

            <Card v-if="portfolio.description">
                <CardHeader>
                    <CardTitle>About</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ portfolio.description }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground">Client</dt>
                            <dd class="font-medium">
                                {{ portfolio.client_name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Type</dt>
                            <dd class="font-medium">
                                {{ portfolio.project_type }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Sort order</dt>
                            <dd class="font-medium tabular-nums">
                                {{ portfolio.sort_order }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Live URL</dt>
                            <dd class="font-medium">
                                <a
                                    v-if="portfolio.live_url"
                                    :href="portfolio.live_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary underline underline-offset-2"
                                >
                                    {{ portfolio.live_url }}
                                </a>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Created</dt>
                            <dd class="font-medium tabular-nums">
                                {{ formatDate(portfolio.created_at) ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Updated</dt>
                            <dd class="font-medium tabular-nums">
                                {{ formatDate(portfolio.updated_at) ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="portfolio.tech_stack.length"
                        class="mt-4 flex flex-wrap gap-1"
                    >
                        <Badge
                            v-for="tech in portfolio.tech_stack"
                            :key="tech"
                            variant="secondary"
                        >
                            {{ tech }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="portfolio.gallery.length">
                <CardHeader>
                    <CardTitle>Gallery</CardTitle>
                    <CardDescription>
                        {{ portfolio.gallery.length }}
                        {{ portfolio.gallery.length === 1 ? 'item' : 'items' }}
                        in display order.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <img
                            v-for="(url, position) in portfolio.gallery"
                            :key="url"
                            :src="url"
                            :alt="`${portfolio.title} gallery image ${position + 1}`"
                            class="aspect-video w-full rounded-lg border border-border object-cover"
                            loading="lazy"
                        />
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>

    <PortfolioFormSheet
        v-if="portfolio"
        v-model:open="sheetOpen"
        :portfolio="portfolio"
    />

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this portfolio?"
        description="It will be soft-deleted — you can restore it afterwards."
        confirm-label="Delete"
        @confirm="confirmDelete"
    />
</template>
