<script setup lang="ts">
import { PlusIcon, Trash2Icon } from '@lucide/vue';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ManualRangeFormValue } from '../schemas/videoEditFormSchema';
import {
    MAX_MANUAL_RANGES,
    MAX_RANGE_NOTE_LENGTH,
} from '../schemas/videoEditFormSchema';

/**
 * The manual "cut this" ranges, in seconds on the merged timeline (D7).
 * Validation lives in the Zod schema; this only edits the list.
 */
const { disabled = false } = defineProps<{ disabled?: boolean }>();

const ranges = defineModel<ManualRangeFormValue[]>({ required: true });

const emit = defineEmits<{ blur: [] }>();

const uid = useId();

function add(): void {
    const lastEnd = ranges.value.at(-1)?.end_seconds ?? 0;

    ranges.value = [
        ...ranges.value,
        { start_seconds: lastEnd, end_seconds: lastEnd + 5, note: '' },
    ];
}

function update(index: number, patch: Partial<ManualRangeFormValue>): void {
    ranges.value = ranges.value.map((range, position) =>
        position === index ? { ...range, ...patch } : range,
    );
}

function remove(index: number): void {
    ranges.value = ranges.value.filter((_, position) => position !== index);
    emit('blur');
}

function toSeconds(value: string | number): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-for="(range, index) in ranges"
            :key="index"
            class="grid grid-cols-[1fr_1fr_auto] items-end gap-2 sm:grid-cols-[6rem_6rem_1fr_auto]"
        >
            <div class="flex flex-col gap-1">
                <label
                    :for="`${uid}-start-${index}`"
                    class="text-xs text-muted-foreground"
                >
                    Start (s)
                </label>
                <Input
                    :id="`${uid}-start-${index}`"
                    type="number"
                    min="0"
                    step="0.1"
                    inputmode="decimal"
                    :disabled="disabled"
                    :model-value="range.start_seconds"
                    @update:model-value="
                        update(index, { start_seconds: toSeconds($event) })
                    "
                    @blur="emit('blur')"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label
                    :for="`${uid}-end-${index}`"
                    class="text-xs text-muted-foreground"
                >
                    End (s)
                </label>
                <Input
                    :id="`${uid}-end-${index}`"
                    type="number"
                    min="0"
                    step="0.1"
                    inputmode="decimal"
                    :disabled="disabled"
                    :model-value="range.end_seconds"
                    @update:model-value="
                        update(index, { end_seconds: toSeconds($event) })
                    "
                    @blur="emit('blur')"
                />
            </div>
            <div
                class="col-span-2 row-start-2 flex flex-col gap-1 sm:col-span-1 sm:row-start-auto"
            >
                <label
                    :for="`${uid}-note-${index}`"
                    class="text-xs text-muted-foreground"
                >
                    Note (optional)
                </label>
                <Input
                    :id="`${uid}-note-${index}`"
                    :maxlength="MAX_RANGE_NOTE_LENGTH"
                    placeholder="Coughing fit"
                    :disabled="disabled"
                    :model-value="range.note"
                    @update:model-value="
                        update(index, { note: String($event) })
                    "
                    @blur="emit('blur')"
                />
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Remove range ${index + 1}`"
                :disabled="disabled"
                @click="remove(index)"
            >
                <Trash2Icon
                    class="size-4 text-destructive"
                    aria-hidden="true"
                />
            </Button>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="self-start"
            :disabled="disabled || ranges.length >= MAX_MANUAL_RANGES"
            @click="add"
        >
            <PlusIcon class="size-4" aria-hidden="true" />
            Add range
        </Button>
    </div>
</template>
