<script setup lang="ts">
import { Eraser } from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import SignaturePad from 'signature_pad';
import { onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * A drawn signature, modelled as an `image/png` data URL.
 *
 * ## Accessibility caveat — read before making a signature required
 *
 * The pad is a pointer-only control: there is no keyboard path to producing a
 * stroke, so a form that accepts nothing else fails WCAG 2.1.1. Where a
 * signature is mandatory, the calling form MUST offer a non-pointer
 * alternative (a typed-name attestation, or an uploaded image). The canvas
 * carries an accessible name and the Clear button is reachable, but naming a
 * control is not the same as making it operable.
 */

/**
 * The wrapper is the root element, but the fallthrough attrs belong on the
 * canvas — `AppField` hands down `id`, `name` and the `aria-*` wiring through
 * `v-bind="control"`, and the `<label for>` has to resolve to the control the
 * user actually operates. Without this, Vue would ALSO apply them to the root
 * `<div>`, putting the same `id` on two elements in one document.
 */
defineOptions({ inheritAttrs: false });

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
 * The last value this component wrote to the model.
 *
 * The model watcher repaints the canvas, and `toDataURL()` output flowing back
 * in would repaint over the strokes that produced it — dropping the internal
 * stroke data in the process, because `fromDataURL` does not rebuild it. This
 * lets the watcher tell an external assignment from its own echo.
 */
let selfEmitted: string | null = null;

/**
 * The data URL currently painted on the canvas, when it did not come from
 * strokes drawn here.
 *
 * `fromDataURL` draws the image without populating the internal stroke data
 * (documented upstream), so `toData()`/`fromData()` cannot carry a hydrated
 * signature across a resize. Keeping the URL gives `resizeCanvas` something to
 * replay in that case.
 */
const paintedUrl = ref<string | null>(null);

/**
 * A canvas backing store is in device pixels, so on a HiDPI screen it must be
 * scaled up or every stroke lands blurry and offset from the cursor.
 */
function canvasRatio(): number {
    return Math.max(window.devicePixelRatio || 1, 1);
}

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

function resizeCanvas(): void {
    const canvas = canvasRef.value;
    const wrapper = wrapperRef.value;

    if (!canvas || !wrapper) {
        return;
    }

    const ratio = canvasRatio();
    const data = pad.value?.toData();
    const hydrated = paintedUrl.value;

    canvas.width = wrapper.clientWidth * ratio;
    canvas.height = height * ratio;
    canvas.style.width = `${wrapper.clientWidth}px`;
    canvas.style.height = `${height}px`;
    canvas.getContext('2d')?.scale(ratio, ratio);

    // Resizing clears the surface, so the strokes go back on afterwards.
    if (data?.length) {
        pad.value?.fromData(data);
    } else if (hydrated) {
        void pad.value?.fromDataURL(hydrated, { ratio });
    }
}

/**
 * Renders a model value the component did not produce itself — a form default,
 * or a `form.reset()` back to a stored signature.
 */
async function applyModel(value: string | null): Promise<void> {
    if (!pad.value) {
        return;
    }

    pad.value.clear();

    if (value === null) {
        paintedUrl.value = null;
        isEmpty.value = true;

        return;
    }

    await pad.value.fromDataURL(value, { ratio: canvasRatio() });
    paintedUrl.value = value;
    isEmpty.value = false;
}

function commit(value: string | null): void {
    selfEmitted = value;
    model.value = value;
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
        // Backed by real stroke data now, so a resize can replay it.
        paintedUrl.value = null;
        commit(
            isEmpty.value ? null : (pad.value?.toDataURL('image/png') ?? null),
        );
    });

    if (disabled) {
        pad.value.off();
    }

    void applyModel(model.value);
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

// An external assignment — a form default, or a reset — has to reach the canvas.
watch(model, (value) => {
    if (value === selfEmitted) {
        return;
    }

    void applyModel(value);
});

function clear(): void {
    pad.value?.clear();
    paintedUrl.value = null;
    isEmpty.value = true;
    commit(null);
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
                :aria-disabled="disabled"
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
                <Eraser class="size-4" aria-hidden="true" />
                {{ clearLabel }}
            </Button>
        </div>
    </div>
</template>
