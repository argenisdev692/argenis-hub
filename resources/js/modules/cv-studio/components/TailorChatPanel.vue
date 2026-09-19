<script setup lang="ts">
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { FilterSelect } from '@/common/form';
import type { FilterSelectOption } from '@/common/form';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTailorChat } from '../composables/useTailorChat';
import type { TailorLanguage } from '../composables/useTailorChat';

const { postingUuid } = defineProps<{
    /** The posting the tailored version is produced for. */
    postingUuid: string;
}>();

const UUID_PATTERN =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const MAX_NOTES = 2000;

const languageOptions: FilterSelectOption[] = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Español' },
    { value: 'pt-PT', label: 'Português (PT)' },
];

const structureUuid = ref('');
const language = ref<TailorLanguage>('en');
const notes = ref('');

const { tailor } = useTailorChat();

const structureValid = computed(() =>
    UUID_PATTERN.test(structureUuid.value.trim()),
);

const notesCount = computed(() => notes.value.length);

function onLanguageChange(
    value: FilterSelectOption['value'] | FilterSelectOption['value'][] | null,
): void {
    if (value === 'en' || value === 'es' || value === 'pt-PT') {
        language.value = value;
    }
}

function submit(): void {
    if (!structureValid.value || tailor.isLoading.value) {
        return;
    }

    tailor.mutate({
        postingUuid,
        structureUuid: structureUuid.value.trim(),
        language: language.value,
        notes: notes.value.trim() ? notes.value.trim() : undefined,
    });
}
</script>

<template>
    <section
        class="rounded-xl border border-border bg-card p-4"
        aria-label="Tailor CV with the agent"
    >
        <h2 class="mb-1 text-sm font-semibold">Tailor CV with the agent</h2>
        <p class="mb-3 text-xs text-muted-foreground">
            Uses a confirmed CV structure — copy its UUID from the Structure
            page. Your notes are advisory: the agent re-words genuinely-held
            skills only, never invents new ones.
        </p>

        <div class="flex flex-col gap-3">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium">Confirmed structure UUID</span>
                <input
                    v-model="structureUuid"
                    type="text"
                    inputmode="text"
                    placeholder="01H…"
                    class="rounded-md border border-border bg-muted/40 px-3 py-2 font-mono text-sm"
                    :aria-invalid="!structureValid && structureUuid !== ''"
                />
            </label>

            <div class="flex flex-col gap-1 text-sm">
                <span class="font-medium">Language</span>
                <FilterSelect
                    class="w-48"
                    :options="languageOptions"
                    :clearable="false"
                    :model-value="language"
                    @update:model-value="onLanguageChange"
                />
            </div>

            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium">
                    Notes for the agent
                    <span class="font-normal text-muted-foreground">
                        ({{ notesCount }}/{{ MAX_NOTES }})
                    </span>
                </span>
                <Textarea
                    v-model="notes"
                    placeholder="Emphasize API work, add the Cloudflare R2 migration, keep the summary under 3 lines…"
                    rows="4"
                    :maxlength="MAX_NOTES"
                    class="text-sm"
                />
            </label>

            <PermissionGuard permission="CREATE_STUDIO_POSTINGS">
                <Button
                    class="w-fit"
                    :disabled="!structureValid || tailor.isLoading.value"
                    :aria-busy="tailor.isLoading.value"
                    @click="submit"
                >
                    {{
                        tailor.isLoading.value
                            ? 'Tailoring…'
                            : 'Tailor to this posting'
                    }}
                </Button>
            </PermissionGuard>
        </div>
    </section>
</template>
