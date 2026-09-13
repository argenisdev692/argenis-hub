<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileTextIcon, UploadCloudIcon } from '@lucide/vue';
import { computed } from 'vue';
import { AppField, FileDropzone, TextField } from '@/common/form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/course-scripts';
import { useCourseForm } from '../composables/useCourseForm';
import { formatBytes } from '../helpers/coursePresentation';
import {
    acceptedTypes,
    maxSizeMb,
    syncContentEntries,
} from '../schemas/courseFormSchema';
import type { CourseContentEntry } from '../schemas/courseFormSchema';
import type { CourseUploadLimits } from '../types';

const { limits } = defineProps<{
    limits: CourseUploadLimits;
}>();

const form = useCourseForm(limits);

const isSubmitting = form.useStore((state) => state.isSubmitting);

const accept = computed(() => acceptedTypes(limits));
const sizeMb = computed(() => maxSizeMb(limits));
const formatsHint = computed(() =>
    limits.allowed_extensions.map((ext) => `.${ext}`).join(', '),
);

/** An empty number input means "not tied to a specific video". */
function toVideoNumber(value: string | number): number | null {
    return value === '' ? null : Number(value);
}

function contentFiles(entries: CourseContentEntry[]): File[] {
    return entries.map((entry) => entry.file);
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="form.handleSubmit()">
        <Card>
            <CardHeader>
                <CardTitle>Course index</CardTitle>
                <CardDescription>
                    The index defines the blocks and videos. The title is read
                    from it when you leave the field empty.
                </CardDescription>
            </CardHeader>

            <CardContent class="grid gap-4">
                <form.Field name="title" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Title"
                        placeholder="Leave empty to use the index title"
                    />
                </form.Field>

                <form.Field name="index" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Index file"
                        required
                        :description="`${formatsHint}, up to ${sizeMb} MB. PDFs need a text layer — scanned files are rejected.`"
                        #default="{ control }"
                    >
                        <FileDropzone
                            v-bind="control"
                            :model-value="field.state.value"
                            :accept="accept"
                            :max-size-mb="sizeMb"
                            :disabled="isSubmitting"
                            :hint="formatsHint"
                            @update:model-value="field.handleChange($event)"
                        />
                    </AppField>
                </form.Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Content files</CardTitle>
                <CardDescription>
                    Optional source material. Tie a file to a video number to
                    ground only that video's script in it.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <form.Field name="contents" mode="array" #default="{ field }">
                    <div class="grid gap-4">
                        <AppField
                            :field="field"
                            label="Files"
                            :description="`Up to ${limits.max_content_files} files.`"
                            #default="{ control }"
                        >
                            <FileDropzone
                                v-bind="control"
                                multiple
                                :max-files="limits.max_content_files"
                                :model-value="contentFiles(field.state.value)"
                                :accept="accept"
                                :max-size-mb="sizeMb"
                                :disabled="isSubmitting"
                                :hint="formatsHint"
                                @update:model-value="
                                    field.handleChange(
                                        syncContentEntries(
                                            $event,
                                            field.state.value,
                                        ),
                                    )
                                "
                            />
                        </AppField>

                        <ul
                            v-if="field.state.value.length > 0"
                            class="divide-y divide-border rounded-lg border border-border"
                        >
                            <li
                                v-for="(entry, position) in field.state.value"
                                :key="`${entry.file.name}-${entry.file.lastModified}`"
                                class="flex flex-col gap-3 p-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <FileTextIcon
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate text-sm font-medium">
                                        {{ entry.file.name }}
                                    </span>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{ formatBytes(entry.file.size) }}
                                    </span>
                                </div>

                                <form.Field
                                    :name="`contents[${position}].video_number`"
                                    #default="{ field: numberField }"
                                >
                                    <AppField
                                        :field="numberField"
                                        label="Video #"
                                        class="sm:w-32"
                                        #default="{ control }"
                                    >
                                        <Input
                                            v-bind="control"
                                            type="number"
                                            min="1"
                                            step="1"
                                            placeholder="Any"
                                            :model-value="
                                                numberField.state.value ?? ''
                                            "
                                            @blur="numberField.handleBlur"
                                            @update:model-value="
                                                numberField.handleChange(
                                                    toVideoNumber($event),
                                                )
                                            "
                                        />
                                    </AppField>
                                </form.Field>
                            </li>
                        </ul>
                    </div>
                </form.Field>
            </CardContent>

            <CardFooter
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button as-child variant="outline" :disabled="isSubmitting">
                    <Link :href="index()">Cancel</Link>
                </Button>

                <Button type="submit" :disabled="isSubmitting">
                    <Spinner v-if="isSubmitting" aria-hidden="true" />
                    <UploadCloudIcon v-else class="size-4" aria-hidden="true" />
                    Upload course
                </Button>
            </CardFooter>
        </Card>
    </form>
</template>
