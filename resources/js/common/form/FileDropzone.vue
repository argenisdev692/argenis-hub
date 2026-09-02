<script setup lang="ts">
import { FileIcon, UploadCloudIcon, XIcon } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const {
    accept = [],
    maxSizeMb = 10,
    multiple = false,
    maxFiles = 5,
    disabled = false,
    hint,
    class: className,
} = defineProps<{
    /**
     * What the picker offers and the client re-checks — the same vocabulary the
     * HTML `accept` attribute uses, so either a MIME type
     * (`'application/pdf'`) or a dot-prefixed extension (`'.md'`). A file
     * matching any entry is accepted.
     *
     * Extensions are not decoration: the OS has no MIME mapping for several
     * plain-text formats, so a `.md` picked on Windows arrives with an empty
     * `File.type` and a MIME-only list would reject it. e.g.
     * `['application/pdf', '.md']`.
     */
    accept?: string[];
    maxSizeMb?: number;
    multiple?: boolean;
    maxFiles?: number;
    disabled?: boolean;
    hint?: string;
    class?: string;
}>();

const model = defineModel<File[]>({ default: () => [] });

const inputId = useId();
const isDragging = ref(false);
const rejections = ref<string[]>([]);

const maxBytes = computed(() => maxSizeMb * 1024 * 1024);
const acceptAttr = computed(() =>
    accept.length ? accept.join(',') : undefined,
);

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Matches `accept` the way the HTML attribute does: MIME type or extension. */
function isAccepted(file: File): boolean {
    const name = file.name.toLowerCase();

    return accept.some((entry) =>
        entry.startsWith('.')
            ? name.endsWith(entry.toLowerCase())
            : entry === file.type,
    );
}

/**
 * Client-side checks are a UX affordance only — the browser can be bypassed
 * trivially, so the server MUST re-validate mime, size and extension. See the
 * matching `FormRequest` rules in the module that consumes this field.
 */
function validate(file: File): string | null {
    if (file.size > maxBytes.value) {
        return `${file.name} is ${formatSize(file.size)} — the limit is ${maxSizeMb} MB.`;
    }

    if (accept.length && !isAccepted(file)) {
        return `${file.name} is not an accepted file type.`;
    }

    return null;
}

function addFiles(files: FileList | null): void {
    if (!files || disabled) {
        return;
    }

    const errors: string[] = [];
    const accepted: File[] = [];

    for (const file of Array.from(files)) {
        const error = validate(file);

        if (error) {
            errors.push(error);

            continue;
        }

        accepted.push(file);
    }

    const next = multiple
        ? [...model.value, ...accepted]
        : accepted.slice(0, 1);

    if (multiple && next.length > maxFiles) {
        errors.push(`You can attach at most ${maxFiles} files.`);
    }

    rejections.value = errors;
    model.value = multiple ? next.slice(0, maxFiles) : next;
}

function onDrop(event: DragEvent): void {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    addFiles(input.files);
    // Reset so re-picking the same file still fires a change event.
    input.value = '';
}

function remove(index: number): void {
    model.value = model.value.filter((_, position) => position !== index);
}
</script>

<template>
    <div :class="cn('flex flex-col gap-3', className)">
        <label
            :for="inputId"
            :class="
                cn(
                    'flex cursor-pointer flex-col items-center gap-2 rounded-md border border-dashed border-input px-4 py-8 text-center transition-colors hover:bg-accent/50',
                    'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    isDragging && 'border-ring bg-accent',
                    disabled && 'pointer-events-none opacity-50',
                )
            "
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <UploadCloudIcon
                class="size-6 text-muted-foreground"
                aria-hidden="true"
            />
            <span class="text-sm font-medium">
                Drop {{ multiple ? 'files' : 'a file' }} here, or browse
            </span>
            <span class="text-xs text-muted-foreground">
                {{
                    hint ??
                    `Up to ${maxSizeMb} MB${multiple ? ` · max ${maxFiles} files` : ''}`
                }}
            </span>
            <input
                :id="inputId"
                type="file"
                class="sr-only"
                :accept="acceptAttr"
                :multiple="multiple"
                :disabled="disabled"
                v-bind="$attrs"
                @change="onPick"
            />
        </label>

        <ul v-if="model.length" class="flex flex-col gap-2">
            <li
                v-for="(file, index) in model"
                :key="`${file.name}-${index}`"
                class="flex items-center gap-2 rounded-md border border-input px-3 py-2"
            >
                <FileIcon
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <span class="flex-1 truncate text-sm">{{ file.name }}</span>
                <span class="shrink-0 text-xs text-muted-foreground">
                    {{ formatSize(file.size) }}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7 shrink-0"
                    :aria-label="`Remove ${file.name}`"
                    :disabled="disabled"
                    @click="remove(index)"
                >
                    <XIcon class="size-4" />
                </Button>
            </li>
        </ul>

        <ul v-if="rejections.length" class="flex flex-col gap-1" role="alert">
            <li
                v-for="message in rejections"
                :key="message"
                class="text-sm text-destructive"
            >
                {{ message }}
            </li>
        </ul>
    </div>
</template>
