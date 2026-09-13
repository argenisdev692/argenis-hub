<script setup lang="ts">
import { computed, useId } from 'vue';
import type { FilterSelectValue } from '@/common/form';
import {
    AppField,
    FileDropzone,
    FilterSelect,
    FormDialog,
    TextField,
} from '@/common/form';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { useVideoEditForm } from '../composables/useVideoEditForm';
import {
    SPEECH_CATEGORY_OPTIONS,
    VIDEO_EDIT_MODES,
    videoEditModePresentation,
} from '../helpers/videoEditPresentation';
import {
    ACCEPTED_SCRIPT_TYPES,
    ACCEPTED_VIDEO_TYPES,
    MAX_SCRIPT_MB,
    MAX_SOURCE_MB,
    MAX_SOURCES,
    SILENCE_THRESHOLD,
} from '../schemas/videoEditFormSchema';
import type { SpeechCategory, VideoEditMode } from '../types';
import VideoEditRangesField from './VideoEditRangesField.vue';

/**
 * Create an edit: pick a mode, attach clips, choose what to cut. Only fields
 * the chosen mode accepts are rendered — the backend prohibits the rest.
 */
const open = defineModel<boolean>('open', { default: false });

const { form, uploadProgress } = useVideoEditForm({ open });

const mode = form.useStore((state) => state.values.mode);
const speechEnabled = form.useStore((state) => state.values.speech_enabled);
const silenceEnabled = form.useStore((state) => state.values.silence_enabled);
const isSubmitting = form.useStore((state) => state.isSubmitting);

const isMerge = computed(() => mode.value === 'merge');
const isAiEdit = computed(() => mode.value === 'ai_edit');

const uploadPercent = computed(() =>
    uploadProgress.value && uploadProgress.value.total > 0
        ? Math.round(
              (uploadProgress.value.uploaded / uploadProgress.value.total) *
                  100,
          )
        : 0,
);

const uid = useId();

function toMode(value: unknown): VideoEditMode {
    return (
        VIDEO_EDIT_MODES.find((candidate) => candidate === value) ?? 'auto_edit'
    );
}

function toCategories(
    value: FilterSelectValue | FilterSelectValue[] | null,
): SpeechCategory[] {
    const values = Array.isArray(value) ? value : [];

    return SPEECH_CATEGORY_OPTIONS.map((option) => option.value).filter(
        (category) => values.includes(category),
    );
}

function toNullableInteger(value: string | number): number | null {
    const parsed = Number(value);

    return value === '' || !Number.isFinite(parsed) ? null : parsed;
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        title="New video edit"
        description="Clips upload straight to private storage, then the edit runs in the background."
        submit-label="Start edit"
        content-class="sm:max-w-2xl max-h-[90dvh] overflow-y-auto"
    >
        <div class="grid gap-5">
            <form.Field name="mode" #default="{ field }">
                <AppField
                    :field="field"
                    label="Mode"
                    required
                    #default="{ control }"
                >
                    <RadioGroup
                        v-bind="control"
                        class="grid gap-2 sm:grid-cols-3"
                        :disabled="isSubmitting"
                        :model-value="field.state.value"
                        @update:model-value="field.handleChange(toMode($event))"
                    >
                        <label
                            v-for="option in VIDEO_EDIT_MODES"
                            :key="option"
                            :for="`${uid}-mode-${option}`"
                            class="flex cursor-pointer flex-col gap-1 rounded-lg border border-border p-3 transition-colors has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-accent"
                        >
                            <span
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <RadioGroupItem
                                    :id="`${uid}-mode-${option}`"
                                    :value="option"
                                />
                                {{ videoEditModePresentation(option).label }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    videoEditModePresentation(option)
                                        .description
                                }}
                            </span>
                        </label>
                    </RadioGroup>
                </AppField>
            </form.Field>

            <form.Field name="sources" #default="{ field }">
                <AppField
                    :field="field"
                    label="Clips"
                    required
                    :description="`Up to ${MAX_SOURCES} clips, joined in the order shown. MP4, MOV, WebM or MKV, ${MAX_SOURCE_MB / 1024} GB each.`"
                    #default="{ control }"
                >
                    <FileDropzone
                        v-bind="control"
                        multiple
                        :max-files="MAX_SOURCES"
                        :max-size-mb="MAX_SOURCE_MB"
                        :accept="ACCEPTED_VIDEO_TYPES"
                        :disabled="isSubmitting"
                        hint="MP4, MOV, WebM or MKV"
                        :model-value="field.state.value"
                        @update:model-value="field.handleChange($event)"
                    />
                </AppField>
            </form.Field>

            <template v-if="!isMerge">
                <form.Field name="silence_enabled" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Remove silences"
                        orientation="horizontal"
                        description="Cuts pauses longer than the threshold below."
                        #default="{ control }"
                    >
                        <Switch
                            v-bind="control"
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="
                                field.handleChange(Boolean($event))
                            "
                        />
                    </AppField>
                </form.Field>

                <form.Field
                    v-if="silenceEnabled"
                    name="silence_threshold_seconds"
                    #default="{ field }"
                >
                    <AppField
                        :field="field"
                        label="Silence threshold (seconds)"
                        :description="`Between ${SILENCE_THRESHOLD.min} and ${SILENCE_THRESHOLD.max} seconds.`"
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="number"
                            inputmode="decimal"
                            class="w-32"
                            :min="SILENCE_THRESHOLD.min"
                            :max="SILENCE_THRESHOLD.max"
                            step="0.1"
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="
                                field.handleChange(Number($event))
                            "
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>

                <form.Field
                    v-if="!isAiEdit"
                    name="speech_enabled"
                    #default="{ field }"
                >
                    <AppField
                        :field="field"
                        label="Clean up speech"
                        orientation="horizontal"
                        description="Transcribes the audio and cuts hesitations, filler words and false starts."
                        #default="{ control }"
                    >
                        <Switch
                            v-bind="control"
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="
                                field.handleChange(Boolean($event))
                            "
                        />
                    </AppField>
                </form.Field>

                <form.Field
                    v-if="isAiEdit || speechEnabled"
                    name="speech_categories"
                    #default="{ field }"
                >
                    <AppField
                        :field="field"
                        label="Speech to remove"
                        description="Leave empty to remove every category."
                        #default="{ control }"
                    >
                        <FilterSelect
                            v-bind="control"
                            multiple
                            class="w-full"
                            placeholder="All categories"
                            :options="SPEECH_CATEGORY_OPTIONS"
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="
                                field.handleChange(toCategories($event))
                            "
                        />
                    </AppField>
                </form.Field>

                <form.Field name="manual_ranges" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Ranges to cut"
                        description="Optional. Seconds on the joined timeline."
                    >
                        <VideoEditRangesField
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="field.handleChange($event)"
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>
            </template>

            <template v-if="isAiEdit">
                <form.Field name="ai_instructions" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Instructions for the AI"
                        multiline
                        :rows="4"
                        placeholder="Keep the intro short, cut every retake, keep the demo section intact."
                        description="Treated as editorial guidance, never as system rules."
                    />
                </form.Field>

                <form.Field
                    name="ai_target_duration_minutes"
                    #default="{ field }"
                >
                    <AppField
                        :field="field"
                        label="Target length (minutes)"
                        description="Optional. The AI aims for it but never cuts content just to hit it."
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="number"
                            inputmode="numeric"
                            class="w-32"
                            min="1"
                            max="180"
                            step="1"
                            :disabled="isSubmitting"
                            :model-value="field.state.value ?? ''"
                            @update:model-value="
                                field.handleChange(toNullableInteger($event))
                            "
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>

                <form.Field name="ai_script" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Script"
                        :description="`Optional. Markdown or PDF, up to ${MAX_SCRIPT_MB} MB.`"
                        #default="{ control }"
                    >
                        <FileDropzone
                            v-bind="control"
                            :accept="ACCEPTED_SCRIPT_TYPES"
                            :max-size-mb="MAX_SCRIPT_MB"
                            :disabled="isSubmitting"
                            hint="Markdown (.md) or PDF"
                            :model-value="field.state.value"
                            @update:model-value="field.handleChange($event)"
                        />
                    </AppField>
                </form.Field>

                <form.Field name="ai_consented" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Send the transcript and script to the AI provider"
                        orientation="horizontal"
                        description="Required for AI edit. Nothing is sent without it."
                        required
                        #default="{ control }"
                    >
                        <Checkbox
                            v-bind="control"
                            :disabled="isSubmitting"
                            :model-value="field.state.value"
                            @update:model-value="
                                field.handleChange($event === true)
                            "
                        />
                    </AppField>
                </form.Field>
            </template>

            <div
                v-if="uploadProgress"
                class="flex flex-col gap-2 rounded-lg border border-border bg-muted/40 p-3"
                role="status"
                aria-live="polite"
            >
                <p class="text-sm">
                    Uploading {{ uploadProgress.uploaded }} of
                    {{ uploadProgress.total }}
                    {{ uploadProgress.total === 1 ? 'file' : 'files' }}… keep
                    this dialog open.
                </p>
                <Progress :model-value="uploadPercent" />
            </div>
        </div>
    </FormDialog>
</template>
