<script setup lang="ts">
import { ImagePlusIcon, RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    ref,
    useId,
    useTemplateRef,
    watch,
} from 'vue';
import { CircleStencil, Cropper, RectangleStencil } from 'vue-advanced-cropper';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

import 'vue-advanced-cropper/dist/style.css';

type CropperResult = { canvas?: HTMLCanvasElement };
type CropperExposed = { getResult: () => CropperResult };

const {
    shape = 'circle',
    outputSize = 512,
    maxSizeMb = 5,
    accept = ['image/png', 'image/jpeg', 'image/webp'],
    disabled = false,
    fallback = '?',
    class: className,
} = defineProps<{
    shape?: 'circle' | 'square';
    /** Longest edge of the exported image, in px. */
    outputSize?: number;
    maxSizeMb?: number;
    accept?: string[];
    disabled?: boolean;
    /** Initials shown before an image is picked. */
    fallback?: string;
    class?: string;
}>();

/** The cropped result, ready to append to a multipart request. */
const model = defineModel<File | null>({ default: null });

const inputId = useId();
const cropperRef = useTemplateRef<CropperExposed>('cropperRef');
const dialogOpen = ref(false);
const sourceUrl = ref<string | null>(null);
const previewUrl = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const isProcessing = ref(false);

const stencil = computed(() =>
    shape === 'circle' ? CircleStencil : RectangleStencil,
);

/** Object URLs leak until explicitly revoked, so every swap frees the old one. */
function setPreview(url: string | null): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = url;
}

function setSource(url: string | null): void {
    if (sourceUrl.value) {
        URL.revokeObjectURL(sourceUrl.value);
    }

    sourceUrl.value = url;
}

watch(model, (file) => {
    if (!file) {
        setPreview(null);
    }
});

onBeforeUnmount(() => {
    setPreview(null);
    setSource(null);
});

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    errorMessage.value = null;

    if (!accept.includes(file.type)) {
        errorMessage.value = 'Choose a PNG, JPEG or WebP image.';

        return;
    }

    if (file.size > maxSizeMb * 1024 * 1024) {
        errorMessage.value = `That image is larger than ${maxSizeMb} MB.`;

        return;
    }

    setSource(URL.createObjectURL(file));
    dialogOpen.value = true;
}

async function applyCrop(): Promise<void> {
    const canvas = cropperRef.value?.getResult().canvas;

    if (!canvas) {
        return;
    }

    isProcessing.value = true;

    // Downscale to the target box before encoding, so a 12MP phone photo does
    // not become a 12MP avatar.
    const scale = Math.min(
        1,
        outputSize / Math.max(canvas.width, canvas.height),
    );
    const target = document.createElement('canvas');
    target.width = Math.round(canvas.width * scale);
    target.height = Math.round(canvas.height * scale);
    target
        .getContext('2d')
        ?.drawImage(canvas, 0, 0, target.width, target.height);

    const blob = await new Promise<Blob | null>((resolve) => {
        target.toBlob((result) => resolve(result), 'image/webp', 0.9);
    });

    isProcessing.value = false;

    if (!blob) {
        errorMessage.value = 'Could not process that image.';

        return;
    }

    model.value = new File([blob], 'avatar.webp', { type: 'image/webp' });
    setPreview(URL.createObjectURL(blob));
    dialogOpen.value = false;
    setSource(null);
}

function clear(): void {
    model.value = null;
    setPreview(null);
    errorMessage.value = null;
}
</script>

<template>
    <div :class="cn('flex flex-col gap-3', className)">
        <div class="flex items-center gap-4">
            <Avatar :class="cn('size-16', shape === 'square' && 'rounded-md')">
                <AvatarImage
                    v-if="previewUrl"
                    :src="previewUrl"
                    alt="Selected avatar"
                />
                <AvatarFallback :class="cn(shape === 'square' && 'rounded-md')">
                    {{ fallback }}
                </AvatarFallback>
            </Avatar>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled"
                    as-child
                >
                    <label :for="inputId" class="cursor-pointer">
                        <component
                            :is="model ? RotateCcwIcon : ImagePlusIcon"
                            class="size-4"
                        />
                        {{ model ? 'Replace' : 'Upload image' }}
                    </label>
                </Button>

                <Button
                    v-if="model"
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="disabled"
                    @click="clear"
                >
                    <Trash2Icon class="size-4" />
                    Remove
                </Button>
            </div>

            <input
                :id="inputId"
                type="file"
                class="sr-only"
                :accept="accept.join(',')"
                :disabled="disabled"
                v-bind="$attrs"
                @change="onPick"
            />
        </div>

        <p v-if="errorMessage" class="text-sm text-destructive" role="alert">
            {{ errorMessage }}
        </p>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Crop image</DialogTitle>
                    <DialogDescription>
                        Drag to reposition and scroll to zoom, then save.
                    </DialogDescription>
                </DialogHeader>

                <Cropper
                    v-if="sourceUrl"
                    ref="cropperRef"
                    :src="sourceUrl"
                    :stencil-component="stencil"
                    :stencil-props="{ aspectRatio: 1 }"
                    class="h-[22rem] rounded-md bg-muted"
                />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="isProcessing"
                        @click="applyCrop"
                    >
                        Save image
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
