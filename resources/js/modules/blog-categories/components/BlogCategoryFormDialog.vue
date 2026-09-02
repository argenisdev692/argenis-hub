<script setup lang="ts">
import { AppField, FileDropzone, FormDialog, TextField } from '@/common/form';
import { useBlogCategoryForm } from '../composables/useBlogCategoryForm';
import {
    ACCEPTED_IMAGE_TYPES,
    MAX_IMAGE_MB,
} from '../schemas/blogCategoryFormSchema';
import type { BlogCategory } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `category` is `null` in create mode; `useBlogCategoryForm` reads it at submit
 * time to decide between `store` and `update`, so this component only has to
 * carry the prop through, not branch on it itself.
 */
const { category = null } = defineProps<{
    category?: BlogCategory | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useBlogCategoryForm({ open, category: () => category });
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="category ? 'Edit blog category' : 'New blog category'"
        description="Groups posts on the blog and on the public landing page."
        submit-label="Save"
        content-class="sm:max-w-xl"
    >
        <div class="grid gap-4">
            <form.Field name="name" #default="{ field }">
                <TextField
                    :field="field"
                    label="Name"
                    required
                    placeholder="Artificial Intelligence"
                    description="Shown as the category heading. Must be unique."
                />
            </form.Field>

            <form.Field name="description" #default="{ field }">
                <TextField
                    :field="field"
                    label="Description"
                    multiline
                    :rows="3"
                    placeholder="Long-form pieces on applied machine learning."
                />
            </form.Field>

            <!--
                The stored image sits above the dropzone rather than inside it:
                the field means "replace this", and showing what is about to be
                replaced is the difference between a confident upload and a
                cancelled one. Hidden in create mode, where there is nothing to
                replace, and once R2 fails to resolve a URL.
            -->
            <div
                v-if="category?.image_url"
                class="flex items-center gap-3 rounded-lg border border-border bg-muted/40 p-3"
            >
                <img
                    :src="category.image_url"
                    :alt="`Current image for ${category.blog_category_name ?? 'this category'}`"
                    class="size-14 rounded-md object-cover"
                    loading="lazy"
                />
                <p class="text-sm text-muted-foreground">
                    Current image. Upload a new one below to replace it.
                </p>
            </div>

            <form.Field name="image" #default="{ field }">
                <AppField
                    :field="field"
                    label="Image"
                    :description="`JPEG, PNG or WebP, up to ${MAX_IMAGE_MB} MB. Leave empty to keep the current image.`"
                    #default="{ control }"
                >
                    <FileDropzone
                        v-bind="control"
                        :model-value="field.state.value"
                        :accept="[...ACCEPTED_IMAGE_TYPES]"
                        :max-size-mb="MAX_IMAGE_MB"
                        @update:model-value="field.handleChange($event)"
                    />
                </AppField>
            </form.Field>
        </div>
    </FormDialog>
</template>
