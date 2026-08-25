<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as z from 'zod';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import {
    AddressInput,
    AppField,
    AvatarCropperInput,
    CurrencyInput,
    DatePickerInput,
    FileDropzone,
    FilterSelect,
    FormDialog,
    PhoneInput,
    SignaturePadInput,
    SortableList,
    useAppForm,
} from '@/common/form';
import type { FilterSelectOption } from '@/common/form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const schema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters.'),
    email: z.email('Enter a valid email address.'),
    bio: z.string().max(280, 'Keep it under 280 characters.').optional(),
    phone: z.string().min(1, 'A phone number is required.').nullable(),
    country: z.string().min(1, 'Pick a country.').nullable(),
    skills: z.array(z.string()).min(1, 'Pick at least one skill.'),
    startsOn: z.string().min(1, 'Pick a start date.').nullable(),
    rate: z.number().min(1, 'Enter a rate.').nullable(),
    newsletter: z.boolean(),
    notifications: z.boolean(),
});

const form = useAppForm({
    defaultValues: {
        name: '',
        email: '',
        bio: '',
        phone: null as string | null,
        country: null as string | null,
        skills: [] as string[],
        startsOn: null as string | null,
        rate: null as number | null,
        newsletter: false,
        notifications: true,
    },
    schema,
    onSubmit: (values) => {
        console.log('Validated payload', values);
    },
});

const countries: FilterSelectOption[] = [
    { value: 'us', label: 'United States' },
    { value: 'ca', label: 'Canada' },
    { value: 'mx', label: 'Mexico' },
    { value: 'es', label: 'Spain' },
    { value: 've', label: 'Venezuela' },
];

/** 5 000 rows, to demonstrate the virtualised path kicking in. */
const manySkills: FilterSelectOption[] = Array.from(
    { length: 5000 },
    (_, index) => ({
        value: `skill-${index}`,
        label: `Skill ${index + 1}`,
    }),
);

/** Stands in for a server-backed lookup. */
function searchUsers(query: string): Promise<FilterSelectOption[]> {
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve(
                Array.from({ length: 8 }, (_, index) => ({
                    value: `user-${query}-${index}`,
                    label: `${query || 'User'} result ${index + 1}`,
                })),
            );
        }, 350);
    });
}

const remoteUser = ref<string | number | null>(null);
const address = ref(null);
const addressText = ref('');
const avatar = ref<File | null>(null);
const attachments = ref<File[]>([]);
const signature = ref<string | null>(null);
const dialogOpen = ref(false);

const tasks = ref([
    { id: 1, title: 'Draft the proposal' },
    { id: 2, title: 'Review the estimate' },
    { id: 3, title: 'Send for signature' },
]);
</script>

<template>
    <Head title="Form kit" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Form kit</h1>
                <p class="text-sm text-muted-foreground">
                    shadcn-vue <code>field</code> primitives · TanStack Form ·
                    Zod 4
                </p>
            </div>
            <ThemeToggle />
        </header>

        <form novalidate @submit.prevent="form.handleSubmit()">
            <Card>
                <CardHeader>
                    <CardTitle>Core fields</CardTitle>
                    <CardDescription>
                        Every control shares one shell, so labelling,
                        description and error wiring are identical across the
                        kit.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <FieldGroup>
                        <form.Field name="name" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Full name"
                                description="As it appears on the contract."
                                required
                                #default="{ control }"
                            >
                                <Input
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    placeholder="Ada Lovelace"
                                    @blur="field.handleBlur"
                                    @update:model-value="
                                        field.handleChange(String($event))
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="email" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Email"
                                required
                                #default="{ control }"
                            >
                                <Input
                                    v-bind="control"
                                    type="email"
                                    :model-value="field.state.value"
                                    placeholder="ada@example.com"
                                    @blur="field.handleBlur"
                                    @update:model-value="
                                        field.handleChange(String($event))
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="bio" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Bio"
                                description="Max 280 characters."
                                #default="{ control }"
                            >
                                <Textarea
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    rows="3"
                                    @blur="field.handleBlur"
                                    @update:model-value="
                                        field.handleChange(String($event))
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="phone" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Phone"
                                description="Stored as E.164 for laravel-phone."
                                required
                                #default="{ control }"
                            >
                                <PhoneInput
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    default-country="US"
                                    @update:model-value="
                                        field.handleChange($event)
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="country" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Country"
                                description="Searchable single select."
                                required
                                #default="{ control }"
                            >
                                <FilterSelect
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    :options="countries"
                                    placeholder="Choose a country"
                                    @update:model-value="
                                        field.handleChange($event as string)
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="skills" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Skills"
                                description="5 000 options — virtualised automatically."
                                required
                                #default="{ control }"
                            >
                                <FilterSelect
                                    v-bind="control"
                                    multiple
                                    :model-value="field.state.value"
                                    :options="manySkills"
                                    placeholder="Choose skills"
                                    @update:model-value="
                                        field.handleChange($event as string[])
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="startsOn" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Start date"
                                required
                                #default="{ control }"
                            >
                                <DatePickerInput
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        field.handleChange($event)
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="rate" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Hourly rate"
                                description="Stored in cents."
                                required
                                #default="{ control }"
                            >
                                <CurrencyInput
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    currency="USD"
                                    @update:model-value="
                                        field.handleChange($event)
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="newsletter" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Subscribe to the newsletter"
                                orientation="horizontal"
                                #default="{ control }"
                            >
                                <Checkbox
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        field.handleChange(Boolean($event))
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="notifications" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Email notifications"
                                orientation="horizontal"
                                #default="{ control }"
                            >
                                <Switch
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        field.handleChange(Boolean($event))
                                    "
                                />
                            </AppField>
                        </form.Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <div class="mt-4 flex gap-2">
                <Button type="submit">Validate</Button>
                <Button type="button" variant="outline" @click="form.reset()">
                    Reset
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    @click="dialogOpen = true"
                >
                    Open modal form
                </Button>
            </div>
        </form>

        <Card>
            <CardHeader>
                <CardTitle>Rich inputs</CardTitle>
                <CardDescription>
                    The controls that wrap the libraries already in
                    package.json.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <div>
                    <p class="mb-2 text-sm font-medium">
                        Remote search (async)
                    </p>
                    <FilterSelect
                        v-model="remoteUser"
                        :fetcher="searchUsers"
                        :min-query-length="2"
                        placeholder="Search users…"
                    />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">
                        Google Places address
                    </p>
                    <AddressInput
                        v-model="address"
                        v-model:text="addressText"
                    />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Avatar cropper</p>
                    <AvatarCropperInput v-model="avatar" fallback="AL" />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Attachments</p>
                    <FileDropzone
                        v-model="attachments"
                        multiple
                        :accept="['image/png', 'image/jpeg', 'application/pdf']"
                    />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Signature</p>
                    <SignaturePadInput v-model="signature" />
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">Sortable list</p>
                    <SortableList v-model="tasks" #default="{ item }">
                        <span class="text-sm">{{ item.title }}</span>
                    </SortableList>
                </div>
            </CardContent>
        </Card>

        <FormDialog
            v-model:open="dialogOpen"
            :form="form"
            title="Edit details"
            description="Dialog on desktop, bottom sheet on mobile — same form instance."
            submit-label="Save changes"
        >
            <FieldGroup>
                <form.Field name="name" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Full name"
                        required
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            :model-value="field.state.value"
                            @blur="field.handleBlur"
                            @update:model-value="
                                field.handleChange(String($event))
                            "
                        />
                    </AppField>
                </form.Field>

                <form.Field name="email" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Email"
                        required
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="email"
                            :model-value="field.state.value"
                            @blur="field.handleBlur"
                            @update:model-value="
                                field.handleChange(String($event))
                            "
                        />
                    </AppField>
                </form.Field>
            </FieldGroup>
        </FormDialog>
    </div>
</template>
