<script setup lang="ts">
import { CheckIcon, CopyIcon } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * A labelled slab of generated text with a copy button.
 *
 * The social and reel outputs are not editable here on purpose: they do not
 * belong to the post record, they belong in LinkedIn and in a video editor.
 * The only useful action is getting them out intact, so that is the only
 * action offered.
 */

const { label, text } = defineProps<{
    label: string;
    text: string;
}>();

const uid = useId();
const { copy, copied, isSupported } = useClipboard({ copiedDuring: 2000 });
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex items-center justify-between gap-2">
            <p :id="uid" class="text-xs font-medium text-muted-foreground">
                {{ label }}
            </p>

            <Button
                v-if="isSupported"
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 gap-1.5 px-2 text-xs"
                :aria-label="`Copy ${label}`"
                @click="copy(text)"
            >
                <component
                    :is="copied ? CheckIcon : CopyIcon"
                    class="size-3.5"
                    aria-hidden="true"
                />
                {{ copied ? 'Copied' : 'Copy' }}
            </Button>
        </div>

        <p
            :aria-labelledby="uid"
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm whitespace-pre-wrap"
        >
            {{ text }}
        </p>
    </div>
</template>
