<script setup lang="ts">
import { PlusIcon, Trash2Icon } from '@lucide/vue';
import { computed, useId } from 'vue';
import {
    AppField,
    DatePickerInput,
    FormSheet,
    SortableList,
    TextField,
} from '@/common/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import { usePortfolioForm } from '../composables/usePortfolioForm';
import { newGalleryRow } from '../schemas/portfolioFormSchema';
import type { Portfolio } from '../types';

/**
 * One sheet for both create and edit.
 *
 * `portfolio` is `null` in create mode; `usePortfolioForm` reads it at submit
 * time to decide between `POST` and `PUT`, so this component only carries the
 * prop through, it does not branch on it.
 */
const { portfolio = null } = defineProps<{
    portfolio?: Portfolio | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = usePortfolioForm({
    open,
    portfolio: () => portfolio,
    onSuccess: () => {
        open.value = false;
    },
});

const isPublicFieldId = useId();

/** `published_at` is a calendar day; a future date would never be "published". */
const today = new Date().toISOString().slice(0, 10);

type GalleryRow = ReturnType<typeof newGalleryRow>;

/** Immutable helpers for the gallery — the field holds `{ id, path }[]`. */
function updateGalleryPath(
    rows: readonly GalleryRow[],
    id: string,
    path: string,
): GalleryRow[] {
    return rows.map((row) => (row.id === id ? { ...row, path } : row));
}

function removeGalleryRow(
    rows: readonly GalleryRow[],
    id: string,
): GalleryRow[] {
    return rows.filter((row) => row.id !== id);
}

const existingCoverUrl = computed(() => portfolio?.cover_url ?? null);
</script>

<template>
    <FormSheet
        v-model:open="open"
        :form="form"
        :title="portfolio ? 'Edit portfolio' : 'New portfolio'"
        description="Only projects that are public and have a published date appear on the landing page."
        submit-label="Save portfolio"
    >
        <div class="flex flex-col gap-8">
            <section class="flex flex-col gap-4">
                <h3
                    class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Project basics
                </h3>

                <form.Field name="title" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Title"
                        required
                        placeholder="Acme booking platform"
                    />
                </form.Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <form.Field name="client_name" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Client name"
                            required
                            placeholder="Acme Inc."
                        />
                    </form.Field>

                    <form.Field name="project_type" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Project type"
                            required
                            placeholder="Web app"
                            description="Short label — e.g. Web app, Landing page, API."
                        />
                    </form.Field>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h3
                    class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Showcase details
                </h3>

                <form.Field name="tech_stack" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Tech stack"
                        description="Press Enter after each entry. Up to 30."
                    >
                        <TagsInput
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value as string[])
                            "
                            @blur="field.handleBlur"
                        >
                            <TagsInputItem
                                v-for="item in field.state.value"
                                :key="item"
                                :value="item"
                            >
                                <TagsInputItemText />
                                <TagsInputItemDelete />
                            </TagsInputItem>

                            <TagsInputInput placeholder="Vue, Laravel, R2…" />
                        </TagsInput>
                    </AppField>
                </form.Field>

                <form.Field name="live_url" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Live URL"
                        type="url"
                        inputmode="url"
                        placeholder="https://example.com"
                    />
                </form.Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <form.Field name="published_at" #default="{ field }">
                        <AppField
                            :field="field"
                            label="Published on"
                            description="Empty = draft, hidden from the public feed."
                        >
                            <DatePickerInput
                                :model-value="field.state.value"
                                :max-value="today"
                                placeholder="Not published"
                                @update:model-value="field.handleChange"
                            />
                        </AppField>
                    </form.Field>

                    <form.Field name="sort_order" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Sort order"
                            type="number"
                            inputmode="numeric"
                            description="Lower numbers show first."
                        />
                    </form.Field>
                </div>

                <form.Field name="is_public" #default="{ field }">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :id="isPublicFieldId"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value === true)
                            "
                        />
                        <label
                            :for="isPublicFieldId"
                            class="text-sm font-medium"
                        >
                            Visible on the public showcase
                        </label>
                    </div>
                </form.Field>

                <form.Field name="description" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Description"
                        multiline
                        :rows="4"
                        placeholder="What the project does and what you built."
                    />
                </form.Field>
            </section>

            <section class="flex flex-col gap-4">
                <h3
                    class="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Media
                </h3>
                <p class="-mt-2 text-sm text-muted-foreground">
                    Paths are R2 object keys (e.g.
                    <code class="rounded bg-muted px-1 py-0.5 text-xs"
                        >portfolios/acme/cover.webp</code
                    >), not uploads.
                </p>

                <form.Field name="cover_path" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Cover path"
                        placeholder="portfolios/acme/cover.webp"
                    />
                </form.Field>

                <img
                    v-if="existingCoverUrl"
                    :src="existingCoverUrl"
                    alt="Current cover image"
                    class="max-h-40 w-full rounded-md border border-border object-cover"
                />

                <form.Field name="video_path" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Video path"
                        placeholder="portfolios/acme/demo.mp4"
                    />
                </form.Field>

                <form.Field name="media" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Gallery"
                        description="Drag to reorder — display order follows this list."
                    >
                        <div class="flex flex-col gap-3">
                            <SortableList
                                :model-value="field.state.value"
                                empty-text="No gallery items yet."
                                @update:model-value="
                                    (rows) =>
                                        field.handleChange(rows as GalleryRow[])
                                "
                            >
                                <template #default="{ item }">
                                    <Input
                                        :model-value="(item as GalleryRow).path"
                                        placeholder="portfolios/acme/1.webp"
                                        aria-label="Gallery item path"
                                        @update:model-value="
                                            (value) =>
                                                field.handleChange(
                                                    updateGalleryPath(
                                                        field.state.value,
                                                        (item as GalleryRow).id,
                                                        String(value),
                                                    ),
                                                )
                                        "
                                    />
                                </template>
                            </SortableList>

                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="field.state.value.length >= 50"
                                    @click="
                                        field.handleChange([
                                            ...field.state.value,
                                            newGalleryRow(),
                                        ])
                                    "
                                >
                                    <PlusIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Add item
                                </Button>

                                <Button
                                    v-if="field.state.value.length > 0"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        field.handleChange(
                                            removeGalleryRow(
                                                field.state.value,
                                                field.state.value[
                                                    field.state.value.length - 1
                                                ].id,
                                            ),
                                        )
                                    "
                                >
                                    <Trash2Icon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Remove last
                                </Button>
                            </div>
                        </div>
                    </AppField>
                </form.Field>
            </section>
        </div>
    </FormSheet>
</template>
