<script setup lang="ts">
import { PlusIcon, Trash2Icon } from '@lucide/vue';
import { useId } from 'vue';
import {
    AppField,
    fieldErrorMessages,
    FormSheet,
    TagsField,
    TextField,
} from '@/common/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { useCourseContentMutations } from '../composables/useCourseContentMutations';
import { useCourseEditForm } from '../composables/useCourseEditForm';
import type {
    CourseBibleFormValues,
    OrganisationRow,
} from '../schemas/courseEditSchemas';
import {
    courseBibleSchema,
    newCharacterRow,
    newOrganisationRow,
    toCourseBibleFormValues,
    toCourseBiblePayload,
} from '../schemas/courseEditSchemas';
import type { CourseBible } from '../types';

/**
 * The course bible: the fictional world every script shares — organisations,
 * recurring characters, audience, tone and banned phrasings. Characters point
 * at organisations by `key`, so renaming an organisation never breaks them.
 */
const { courseUuid, bible = null } = defineProps<{
    courseUuid: string;
    bible?: CourseBible | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { updateBible } = useCourseContentMutations(() => courseUuid);

const form = useCourseEditForm<CourseBibleFormValues>({
    open,
    schema: courseBibleSchema,
    seed: () => toCourseBibleFormValues(bible),
    submit: (values) => updateBible.mutateAsync(toCourseBiblePayload(values)),
});

const organisations = form.useStore((state) => state.values.organisations);

const organisationsHeadingId = useId();

/** Exactly one primary: checking a row unchecks every other one. */
function withPrimary(
    rows: readonly OrganisationRow[],
    key: string,
): OrganisationRow[] {
    return rows.map((row) => ({ ...row, is_primary: row.key === key }));
}

/** Removing an organisation also unlinks the characters that pointed at it. */
function unlinkCharacters(key: string): void {
    form.setFieldValue('characters', (characters) =>
        characters.map((character) =>
            character.organisation_key === key
                ? { ...character, organisation_key: null }
                : character,
        ),
    );
}

function toOrganisationKey(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :form="form"
        title="Course bible"
        description="Shared by every script of the course. Scripts generated before a change keep the bible revision they used."
        content-class="sm:max-w-2xl"
    >
        <div class="grid gap-6">
            <section class="grid gap-4">
                <form.Field name="audience" #default="{ field }">
                    <TextField :field="field" label="Audience" multiline />
                </form.Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <form.Field name="tone" #default="{ field }">
                        <TextField :field="field" label="Tone" />
                    </form.Field>

                    <form.Field name="taught_tool" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Tool taught"
                            placeholder="e.g. Laravel"
                        />
                    </form.Field>
                </div>

                <form.Field name="forbidden_phrasings" #default="{ field }">
                    <TagsField :field="field" label="Forbidden phrasings" />
                </form.Field>
            </section>

            <Separator />

            <form.Field
                name="organisations"
                mode="array"
                #default="{ field: list }"
            >
                <section
                    class="grid gap-3"
                    :aria-labelledby="organisationsHeadingId"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3
                            :id="organisationsHeadingId"
                            class="text-sm font-semibold"
                        >
                            Organisations
                        </h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="
                                list.pushValue(
                                    newOrganisationRow(list.state.value),
                                )
                            "
                        >
                            <PlusIcon class="size-4" aria-hidden="true" />
                            Add organisation
                        </Button>
                    </div>

                    <p
                        v-for="message in fieldErrorMessages(list)"
                        :key="message"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ message }}
                    </p>

                    <p
                        v-if="list.state.value.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No organisations yet.
                    </p>

                    <div
                        v-for="(organisation, position) in list.state.value"
                        :key="organisation.key"
                        class="grid gap-3 rounded-lg border border-border p-3"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <form.Field
                                :name="`organisations[${position}].name`"
                                #default="{ field }"
                            >
                                <TextField
                                    :field="field"
                                    label="Name"
                                    required
                                />
                            </form.Field>
                            <form.Field
                                :name="`organisations[${position}].sector`"
                                #default="{ field }"
                            >
                                <TextField :field="field" label="Sector" />
                            </form.Field>
                        </div>

                        <form.Field
                            :name="`organisations[${position}].role`"
                            #default="{ field }"
                        >
                            <TextField
                                :field="field"
                                label="Role in the course"
                            />
                        </form.Field>

                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="organisation.is_primary"
                                    @update:model-value="
                                        $event === true &&
                                        list.handleChange(
                                            withPrimary(
                                                list.state.value,
                                                organisation.key,
                                            ),
                                        )
                                    "
                                />
                                Primary organisation
                            </label>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="`Remove organisation ${organisation.name || position + 1}`"
                                @click="
                                    unlinkCharacters(organisation.key);
                                    list.removeValue(position);
                                "
                            >
                                <Trash2Icon
                                    class="size-4 text-destructive"
                                    aria-hidden="true"
                                />
                                Remove
                            </Button>
                        </div>
                    </div>
                </section>
            </form.Field>

            <Separator />

            <form.Field
                name="characters"
                mode="array"
                #default="{ field: list }"
            >
                <section class="grid gap-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">Characters</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="list.pushValue(newCharacterRow())"
                        >
                            <PlusIcon class="size-4" aria-hidden="true" />
                            Add character
                        </Button>
                    </div>

                    <p
                        v-for="message in fieldErrorMessages(list)"
                        :key="message"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ message }}
                    </p>

                    <p
                        v-if="list.state.value.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No recurring characters yet.
                    </p>

                    <div
                        v-for="(character, position) in list.state.value"
                        :key="position"
                        class="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end"
                    >
                        <form.Field
                            :name="`characters[${position}].name`"
                            #default="{ field }"
                        >
                            <TextField :field="field" label="Name" required />
                        </form.Field>

                        <form.Field
                            :name="`characters[${position}].role`"
                            #default="{ field }"
                        >
                            <TextField :field="field" label="Role" />
                        </form.Field>

                        <form.Field
                            :name="`characters[${position}].organisation_key`"
                            #default="{ field }"
                        >
                            <AppField
                                :field="field"
                                label="Organisation"
                                #default="{ control }"
                            >
                                <NativeSelect
                                    v-bind="control"
                                    :model-value="field.state.value ?? ''"
                                    @update:model-value="
                                        field.handleChange(
                                            toOrganisationKey($event),
                                        )
                                    "
                                >
                                    <NativeSelectOption value=""
                                        >None</NativeSelectOption
                                    >
                                    <NativeSelectOption
                                        v-for="organisation in organisations"
                                        :key="organisation.key"
                                        :value="organisation.key"
                                    >
                                        {{
                                            organisation.name ||
                                            'Unnamed organisation'
                                        }}
                                    </NativeSelectOption>
                                </NativeSelect>
                            </AppField>
                        </form.Field>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove character ${character.name || position + 1}`"
                            @click="list.removeValue(position)"
                        >
                            <Trash2Icon
                                class="size-4 text-destructive"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                </section>
            </form.Field>
        </div>
    </FormSheet>
</template>
