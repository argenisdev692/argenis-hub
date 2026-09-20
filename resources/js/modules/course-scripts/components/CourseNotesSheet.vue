<script setup lang="ts">
import { FormSheet, TextField } from '@/common/form';
import { useCourseContentMutations } from '../composables/useCourseContentMutations';
import { useCourseEditForm } from '../composables/useCourseEditForm';
import type { CourseNotesFormValues } from '../schemas/courseEditSchemas';
import {
    courseNotesSchema,
    toCourseNotesFormValues,
    toCourseNotesPayload,
} from '../schemas/courseEditSchemas';
import type { CourseDetail } from '../types';

/** Free-form guidance every script of the course is generated with. */
const { course } = defineProps<{
    course: CourseDetail;
}>();

const open = defineModel<boolean>('open', { default: false });

const { updateCourseNotes } = useCourseContentMutations(() => course.uuid);

const form = useCourseEditForm<CourseNotesFormValues>({
    open,
    schema: courseNotesSchema,
    seed: () => toCourseNotesFormValues(course),
    submit: (values) =>
        updateCourseNotes.mutateAsync(toCourseNotesPayload(values)),
});
</script>

<template>
    <FormSheet
        v-model:open="open"
        :form="form"
        title="Course notes"
        description="Applied to every video's script: tone, context, what to stress or avoid across the whole course."
    >
        <form.Field name="course_notes" #default="{ field }">
            <TextField
                :field="field"
                label="Notes"
                multiline
                :rows="14"
                placeholder="e.g. Examples use a fictional logistics company; avoid jargon before block 2."
            />
        </form.Field>
    </FormSheet>
</template>
