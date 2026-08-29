<script setup lang="ts">
import { Loader2Icon } from '@lucide/vue';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { DialogForm } from './FormDialog.vue';

/**
 * A `FormDialog` that arrives as a side sheet instead of a centred modal.
 *
 * Same contract as `FormDialog` — a `useAppForm` result in, fields in the
 * default slot, dirty-guarded close — for the cases where a form has enough
 * fields (or enough sections) that a modal fights the content. A roomy panel
 * that keeps the list visible behind it reads better for record editing than a
 * dialog that blacks the page out.
 */

const {
    form,
    title,
    description,
    submitLabel = 'Save',
    cancelLabel = 'Cancel',
    /** Ask before discarding edits. Turn off for read-only or trivial sheets. */
    guardDirty = true,
    contentClass,
} = defineProps<{
    form: DialogForm;
    title: string;
    description?: string;
    submitLabel?: string;
    cancelLabel?: string;
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

const isSubmitting = form.useStore((state) => state.isSubmitting);
const isDirty = form.useStore((state) => state.isDirty);
const canSubmit = form.useStore((state) => state.canSubmit);

const confirmDiscardOpen = ref(false);

const submitDisabled = computed(() => isSubmitting.value || !canSubmit.value);

function requestClose(next: boolean): void {
    if (next) {
        open.value = true;

        return;
    }

    if (guardDirty && isDirty.value && !isSubmitting.value) {
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
    <Sheet :open="open" @update:open="requestClose">
        <SheetContent
            side="right"
            :class="cn('w-full gap-0 p-0 sm:max-w-xl', contentClass)"
        >
            <SheetHeader class="border-b border-border">
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription v-if="description">
                    {{ description }}
                </SheetDescription>
            </SheetHeader>

            <form
                class="flex min-h-0 flex-1 flex-col"
                novalidate
                @submit="onSubmit"
            >
                <div class="flex-1 overflow-y-auto px-4 py-5">
                    <slot />
                </div>

                <SheetFooter
                    class="flex-row justify-end gap-2 border-t border-border"
                >
                    <slot name="footer">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSubmitting"
                            @click="requestClose(false)"
                        >
                            {{ cancelLabel }}
                        </Button>
                        <Button type="submit" :disabled="submitDisabled">
                            <Loader2Icon
                                v-if="isSubmitting"
                                class="animate-spin"
                            />
                            {{ submitLabel }}
                        </Button>
                    </slot>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>

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
