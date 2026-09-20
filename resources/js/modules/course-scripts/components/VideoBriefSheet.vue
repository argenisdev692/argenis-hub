<script setup lang="ts">
import { AppField, FormSheet, TagsField, TextField } from '@/common/form';
import { Input } from '@/components/ui/input';
import { useCourseContentMutations } from '../composables/useCourseContentMutations';
import { useCourseEditForm } from '../composables/useCourseEditForm';
import type { VideoBriefFormValues } from '../schemas/courseEditSchemas';
import {
    emptyVideoBriefFormValues,
    toVideoBriefFormValues,
    toVideoBriefPayload,
    videoBriefSchema,
} from '../schemas/courseEditSchemas';
import type { CourseVideo } from '../types';

/**
 * Edits one video's brief — what its script is generated from. Saving a
 * complete brief is also how a "needs review" video is cleared.
 *
 * `video` is null while no row is picked; the sheet only opens with one.
 */
const { courseUuid, video = null } = defineProps<{
    courseUuid: string;
    video?: CourseVideo | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { updateVideoBrief } = useCourseContentMutations(() => courseUuid);

const form = useCourseEditForm<VideoBriefFormValues>({
    open,
    schema: videoBriefSchema,
    seed: () =>
        video ? toVideoBriefFormValues(video) : emptyVideoBriefFormValues(),
    submit: async (values) => {
        if (!video) {
            return;
        }

        await updateVideoBrief.mutateAsync({
            videoUuid: video.uuid,
            payload: toVideoBriefPayload(values),
        });
    },
});

/** An empty number input means "use the course default length". */
function toMinutes(value: string | number): number | null {
    return value === '' ? null : Number(value);
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :form="form"
        :title="video ? `Video ${video.number} brief` : 'Video brief'"
        description="Scripts are generated from this brief. Regenerate the video afterwards to apply changes."
        content-class="sm:max-w-2xl"
    >
        <div class="grid gap-4">
            <form.Field name="title" #default="{ field }">
                <TextField :field="field" label="Title" required />
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <form.Field name="topic" #default="{ field }">
                    <TextField :field="field" label="Topic" />
                </form.Field>

                <form.Field
                    name="declared_duration_minutes"
                    #default="{ field }"
                >
                    <AppField
                        :field="field"
                        label="Length (min)"
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="number"
                            min="1"
                            max="180"
                            step="1"
                            placeholder="Default"
                            :model-value="field.state.value ?? ''"
                            @blur="field.handleBlur"
                            @update:model-value="
                                field.handleChange(toMinutes($event))
                            "
                        />
                    </AppField>
                </form.Field>
            </div>

            <form.Field name="objective" #default="{ field }">
                <TextField :field="field" label="Objective" multiline />
            </form.Field>

            <form.Field name="learning_areas" #default="{ field }">
                <TagsField :field="field" label="Learning areas" />
            </form.Field>

            <form.Field name="audience_objectives" #default="{ field }">
                <TagsField :field="field" label="Audience objectives" />
            </form.Field>

            <form.Field name="mandatory_content" #default="{ field }">
                <TagsField :field="field" label="Mandatory content" />
            </form.Field>

            <form.Field name="errors_to_avoid" #default="{ field }">
                <TagsField :field="field" label="Errors to avoid" />
            </form.Field>

            <form.Field name="expected_result" #default="{ field }">
                <TextField :field="field" label="Expected result" multiline />
            </form.Field>

            <form.Field name="notes" #default="{ field }">
                <TextField :field="field" label="Notes" multiline :rows="5" />
            </form.Field>
        </div>
    </FormSheet>
</template>
