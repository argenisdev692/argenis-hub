<script setup lang="ts">
import { Loader2Icon } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';
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
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { cn } from '@/lib/utils';

/**
 * The slice of a TanStack form this dialog drives. Structural on purpose — it
 * accepts any `useAppForm` result without dragging its 20-odd generics along.
 */
type DialogFormState = {
    isSubmitting: boolean;
    isDefaultValue: boolean;
    canSubmit: boolean;
};

export type DialogForm = {
    handleSubmit: () => Promise<void>;
    reset: () => void;
    useStore: <TSelected>(
        selector: (state: DialogFormState) => TSelected,
    ) => Readonly<Ref<TSelected>>;
};

const {
    form,
    title,
    description,
    submitLabel = 'Save',
    cancelLabel = 'Cancel',
    destructive = false,
    /** Ask before discarding edits. Turn off for read-only or trivial dialogs. */
    guardDirty = true,
    /**
     * Extra classes for the panel itself. Height is NOT the caller's job: the
     * panel is capped to the viewport, the header/footer are pinned and the
     * body scrolls, so pass width or padding overrides only.
     */
    contentClass,
} = defineProps<{
    form: DialogForm;
    title: string;
    description?: string;
    submitLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    guardDirty?: boolean;
    contentClass?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** The form fields. */
    default: () => unknown;
    /** Replaces the default Cancel/Submit pair. */
    footer?: () => unknown;
}>();

/**
 * Below `md` a centred dialog fights the on-screen keyboard, so the same form
 * is presented as a bottom sheet instead. Identical slots, identical state.
 */
const isDesktop = useMediaQuery('(min-width: 768px)');

const isSubmitting = form.useStore((state) => state.isSubmitting);
/**
 * `isDirty` is persistent in TanStack Form — it stays `true` after a no-op
 * `handleChange` (e.g. a `Select` re-emitting its current value) or an edit
 * the user reverted, which made Cancel on an untouched form open the discard
 * prompt. `isDefaultValue` compares against the seeded values instead.
 */
const hasUnsavedChanges = form.useStore((state) => !state.isDefaultValue);
const canSubmit = form.useStore((state) => state.canSubmit);

const confirmDiscardOpen = ref(false);

const submitDisabled = computed(() => isSubmitting.value || !canSubmit.value);

function requestClose(next: boolean): void {
    if (next) {
        open.value = true;

        return;
    }

    if (guardDirty && hasUnsavedChanges.value && !isSubmitting.value) {
        confirmDiscardOpen.value = true;

        return;
    }

    open.value = false;
}

function discard(): void {
    confirmDiscardOpen.value = false;
    form.reset();
    open.value = false;
}

async function onSubmit(event: Event): Promise<void> {
    event.preventDefault();
    await form.handleSubmit();
}
</script>

<template>
    <Dialog v-if="isDesktop" :open="open" @update:open="requestClose">
        <DialogContent
            :class="
                cn(
                    'flex max-h-[calc(100dvh-2rem)] flex-col sm:max-w-lg',
                    contentClass,
                )
            "
        >
            <DialogHeader class="shrink-0">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <form
                class="flex min-h-0 flex-1 flex-col"
                novalidate
                @submit="onSubmit"
            >
                <div class="min-h-0 flex-1 overflow-y-auto px-1 py-1">
                    <slot />
                </div>

                <DialogFooter class="shrink-0">
                    <slot name="footer">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSubmitting"
                            @click="requestClose(false)"
                        >
                            {{ cancelLabel }}
                        </Button>
                        <Button
                            type="submit"
                            :variant="destructive ? 'destructive' : 'default'"
                            :disabled="submitDisabled"
                        >
                            <Loader2Icon
                                v-if="isSubmitting"
                                class="animate-spin"
                            />
                            {{ submitLabel }}
                        </Button>
                    </slot>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Drawer v-else :open="open" @update:open="requestClose">
        <DrawerContent :class="contentClass">
            <DrawerHeader class="shrink-0 text-left">
                <DrawerTitle>{{ title }}</DrawerTitle>
                <DrawerDescription v-if="description">
                    {{ description }}
                </DrawerDescription>
            </DrawerHeader>

            <form
                class="flex min-h-0 flex-1 flex-col"
                novalidate
                @submit="onSubmit"
            >
                <div class="min-h-0 flex-1 overflow-y-auto px-4">
                    <slot />
                </div>

                <DrawerFooter class="shrink-0">
                    <slot name="footer">
                        <Button
                            type="submit"
                            :variant="destructive ? 'destructive' : 'default'"
                            :disabled="submitDisabled"
                        >
                            <Loader2Icon
                                v-if="isSubmitting"
                                class="animate-spin"
                            />
                            {{ submitLabel }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSubmitting"
                            @click="requestClose(false)"
                        >
                            {{ cancelLabel }}
                        </Button>
                    </slot>
                </DrawerFooter>
            </form>
        </DrawerContent>
    </Drawer>

    <AlertDialog v-model:open="confirmDiscardOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Discard unsaved changes?</AlertDialogTitle>
                <AlertDialogDescription>
                    You have edits in this form that have not been saved.
                    Closing now will lose them.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Keep editing</AlertDialogCancel>
                <AlertDialogAction variant="destructive" @click="discard">
                    Discard
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
