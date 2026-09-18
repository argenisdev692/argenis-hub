<script setup lang="ts">
import { ExternalLinkIcon, MoreHorizontalIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { POSTING_STAGE_OPTIONS } from '../helpers/studioPresentation';
import type { StudioPosting, StudioPostingStage } from '../types';

/**
 * The overflow menu beside the inline View / Suspend icons: pipeline moves
 * and the original link — actions too numerous for one icon each.
 */
const {
    posting,
    canUpdate = false,
    busy = false,
} = defineProps<{
    posting: StudioPosting;
    canUpdate?: boolean;
    busy?: boolean;
}>();

const emit = defineEmits<{
    changeStage: [stage: StudioPostingStage];
}>();

/** Only http(s) links are rendered — a stored `javascript:` URL never becomes clickable. */
const originalUrl = computed<string | null>(() => {
    if (!posting.canonical_url) {
        return null;
    }

    try {
        const url = new URL(posting.canonical_url);

        return url.protocol === 'https:' || url.protocol === 'http:'
            ? url.href
            : null;
    } catch {
        return null;
    }
});

function isStage(value: unknown): value is StudioPostingStage {
    return POSTING_STAGE_OPTIONS.some((option) => option.value === value);
}

function onStageSelect(value: unknown): void {
    if (isStage(value) && value !== posting.status) {
        emit('changeStage', value);
    }
}
</script>

<template>
    <DropdownMenu v-if="originalUrl || canUpdate">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-8"
                :disabled="busy"
                :aria-label="`More actions for ${posting.title}`"
            >
                <MoreHorizontalIcon class="size-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuItem v-if="originalUrl" as-child>
                <a
                    :href="originalUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <ExternalLinkIcon class="size-4" aria-hidden="true" />
                    Open original posting
                </a>
            </DropdownMenuItem>

            <template v-if="canUpdate">
                <DropdownMenuSeparator v-if="originalUrl" />
                <DropdownMenuLabel>Move to stage</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    :model-value="posting.status"
                    @update:model-value="onStageSelect"
                >
                    <DropdownMenuRadioItem
                        v-for="option in POSTING_STAGE_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
