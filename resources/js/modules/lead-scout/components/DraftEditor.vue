<script setup lang="ts">
import { CopyIcon, SparklesIcon, TriangleAlertIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { AiCatalogOption } from '../types';

const {
    body,
    subject,
    provider,
    model,
    options,
    unconfirmedClaims,
    generating = false,
} = defineProps<{
    body: string;
    subject: string;
    provider: string | null;
    model: string | null;
    options: AiCatalogOption[];
    unconfirmedClaims: string[];
    generating?: boolean;
}>();

const emit = defineEmits<{
    'update:body': [value: string];
    'update:provider': [value: string];
    'update:model': [value: string];
    generate: [];
    copy: [];
    save: [];
}>();

const localBody = ref(body);
watch(
    () => body,
    (value) => {
        localBody.value = value;
    },
);

const selectedProvider = ref(provider ?? '');
const selectedModel = ref(model ?? '');

const modelOptions = computed(() =>
    options.filter((option) => option.provider === selectedProvider.value),
);

function chooseProvider(value: string): void {
    selectedProvider.value = value;
    const first = modelOptions.value.find((option) => option.available)
        ?? modelOptions.value[0];

    selectedModel.value = first?.model ?? '';
    emit('update:provider', selectedProvider.value);
    emit('update:model', selectedModel.value);
}

function estimatedCost(option: AiCatalogOption): string {
    return `$${option.est_cost_per_100_usd.toFixed(4)} / 100 uses`;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-2">
                <Label for="draft-provider">AI provider</Label>
                <Select
                    id="draft-provider"
                    :model-value="selectedProvider"
                    @update:model-value="chooseProvider(String($event))"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Provider" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in [...new Map(options.map((item) => [item.provider, item])).values()]"
                            :key="option.provider"
                            :value="option.provider"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-2">
                <Label for="draft-model">Model</Label>
                <Select
                    id="draft-model"
                    :model-value="selectedModel"
                    @update:model-value="
                        selectedModel = String($event);
                        emit('update:model', selectedModel);
                    "
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Model" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in modelOptions"
                            :key="option.model"
                            :value="option.model"
                            :disabled="!option.available"
                        >
                            {{ option.label }} · {{ estimatedCost(option) }}
                            {{ option.available ? '' : '(no key)' }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div
            v-if="unconfirmedClaims.length > 0"
            class="flex items-start gap-2 rounded-lg border border-border p-3 text-sm"
            role="alert"
        >
            <TriangleAlertIcon class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                Unconfirmed capabilities (not in the profile):
                {{ unconfirmedClaims.join(', ') }}. Confirm them before
                marking ready.
            </span>
        </div>

        <div class="flex flex-col gap-2">
            <Label for="draft-body">Draft — the art. 14 notice below is mandatory</Label>
            <Textarea
                id="draft-body"
                v-model="localBody"
                rows="14"
                @update:model-value="emit('update:body', String($event))"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                :disabled="generating"
                @click="emit('generate')"
            >
                <SparklesIcon class="size-4" aria-hidden="true" />
                {{ generating ? 'Generating…' : 'Regenerate opener' }}
            </Button>
            <Button
                variant="outline"
                @click="emit('copy')"
            >
                <CopyIcon class="size-4" aria-hidden="true" />
                Copy subject + body
            </Button>
            <Button
                variant="secondary"
                @click="emit('save')"
            >
                Save draft
            </Button>
        </div>

        <p class="text-xs text-muted-foreground">
            Subject: {{ subject }}. Nothing is sent automatically — copy the
            text, send it by hand from your mailbox, then mark it sent.
        </p>
    </div>
</template>
