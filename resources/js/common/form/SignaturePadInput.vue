<script setup lang="ts">
import { Eraser } from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import SignaturePad from 'signature_pad';
import { onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const {
    height = 180,
    penColor,
    disabled = false,
    clearLabel = 'Clear signature',
    class: className,
} = defineProps<{
    height?: number;
    /** Defaults to the current theme's foreground token. */
    penColor?: string;
    disabled?: boolean;
    clearLabel?: string;
    class?: string;
}>();

/** A `image/png` data URL, or null while the pad is empty. */
const model = defineModel<string | null>({ default: null });

const canvasRef = useTemplateRef<HTMLCanvasElement>('canvasRef');
const wrapperRef = useTemplateRef<HTMLDivElement>('wrapperRef');
const pad = ref<SignaturePad | null>(null);
const isEmpty = ref(true);

/**
 * Reads the live token value so the stroke follows the theme instead of being
 * baked to a hex — the whole pad is repainted on theme change by the caller
 * remounting, but a fresh read here keeps the first paint correct.
 */
function resolvePenColor(): string {
    if (penColor) {
        return penColor;
    }

    const foreground = getComputedStyle(document.documentElement)
        .getPropertyValue('--foreground')
        .trim();

    return foreground || '#000000';
}

/**
 * A canvas backing store is in device pixels, so on a HiDPI screen it must be
 * scaled up or every stroke lands blurry and offset from the cursor.
 */
function resizeCanvas(): void {
    const canvas = canvasRef.value;
    const wrapper = wrapperRef.value;

    if (!canvas || !wrapper) {
        return;
    }

    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const data = pad.value?.toData();

    canvas.width = wrapper.clientWidth * ratio;
    canvas.height = height * ratio;
    canvas.style.width = `${wrapper.clientWidth}px`;
    canvas.style.height = `${height}px`;
    canvas.getContext('2d')?.scale(ratio, ratio);

    // Resizing clears the surface, so the strokes go back on afterwards.
    if (data?.length) {
        pad.value?.fromData(data);
    }
}

onMounted(() => {
    if (!canvasRef.value) {
        return;
    }

    pad.value = new SignaturePad(canvasRef.value, {
        penColor: resolvePenColor(),
        backgroundColor: 'rgba(0,0,0,0)',
    });

    resizeCanvas();

    pad.value.addEventListener('endStroke', () => {
        isEmpty.value = pad.value?.isEmpty() ?? true;
        model.value = isEmpty.value
            ? null
            : (pad.value?.toDataURL('image/png') ?? null);
    });

    if (disabled) {
        pad.value.off();
    }
});

onBeforeUnmount(() => {
    pad.value?.off();
    pad.value = null;
});

useResizeObserver(wrapperRef, () => resizeCanvas());

watch(
    () => disabled,
    (isDisabled) => {
        if (isDisabled) {
            pad.value?.off();
        } else {
            pad.value?.on();
        }
    },
);

// An external reset (form.reset()) has to wipe the canvas too.
watch(model, (value) => {
    if (value === null && !(pad.value?.isEmpty() ?? true)) {
        pad.value?.clear();
        isEmpty.value = true;
    }
});

function clear(): void {
    pad.value?.clear();
    isEmpty.value = true;
    model.value = null;
}
</script>

<template>
    <div :class="cn('flex flex-col gap-2', className)">
        <div
            ref="wrapperRef"
            :class="
                cn(
                    'relative w-full rounded-md border border-input bg-background',
                    'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    disabled && 'pointer-events-none opacity-50',
                )
            "
        >
            <canvas
                ref="canvasRef"
                class="touch-none rounded-md"
                aria-label="Signature drawing area"
                role="img"
                v-bind="$attrs"
            />
            <p
                v-if="isEmpty"
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
            >
                Sign here
            </p>
        </div>

        <div class="flex justify-end">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="disabled || isEmpty"
                @click="clear"
            >
                <Eraser class="size-4" />
                {{ clearLabel }}
            </Button>
        </div>
    </div>
</template>
