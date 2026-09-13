<script setup lang="ts">
import { Loader2Icon } from '@lucide/vue';
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

const {
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    destructive = false,
    onConfirm,
} = defineProps<{
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Styles the confirm button as destructive. Use for delete, not restore. */
    destructive?: boolean;
    /**
     * Bound as `@confirm`. Declared as a prop rather than an emit because
     * `emit()` returns `void` — only a prop lets the dialog await the handler,
     * stay busy until it settles and swallow a second click meanwhile.
     */
    onConfirm?: () => unknown;
}>();

const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** Extra context between the description and the buttons. */
    default?: () => unknown;
}>();

const isPending = ref(false);

/**
 * `AlertDialogAction` closes the dialog on click by default, which would rip
 * the pending state off screen mid-request and leave the user unsure whether
 * anything happened. The click is intercepted so the dialog owns its own
 * lifetime: it closes when the parent flips `open`, after the work is done.
 */
async function handleConfirm(event: Event): Promise<void> {
    event.preventDefault();

    if (isPending.value) {
        return;
    }

    isPending.value = true;

    try {
        await onConfirm?.();
    } finally {
        isPending.value = false;
    }
}
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description">
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <slot />

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isPending">
                    {{ cancelLabel }}
                </AlertDialogCancel>
                <AlertDialogAction
                    autofocus
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="isPending"
                    @click="handleConfirm"
                >
                    <Loader2Icon v-if="isPending" class="animate-spin" />
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
