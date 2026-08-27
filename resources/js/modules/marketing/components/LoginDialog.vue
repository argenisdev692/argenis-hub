<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { watch } from 'vue';
import { AppField, useAppForm } from '@/common/form';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import {
    LOGIN_FORM_DEFAULTS,
    loginFormSchema,
} from '../schemas/loginFormSchema';

/**
 * Sign-in without leaving the landing page.
 *
 * Client-side validation is a courtesy only — the POST goes to the same
 * Fortify endpoint `/login` uses, so rate limiting, credential checking and
 * the two-factor challenge are unchanged. `/login` remains a real page for
 * deep links, bookmarks and no-JS clients.
 */
const open = defineModel<boolean>('open', { default: false });

const form = useAppForm({
    defaultValues: { ...LOGIN_FORM_DEFAULTS },
    schema: loginFormSchema,
    submit: {
        target: store(),
        // Fortify redirects to the dashboard on success; a toast on top of a
        // full page change is noise.
        successMessage: null,
    },
});

const isSubmitting = form.useStore((state) => state.isSubmitting);

/**
 * Never leave a password sitting in component state once the dialog is closed
 * — reopening it must start from a blank form, not a filled one.
 */
watch(open, (isOpen) => {
    if (!isOpen) {
        form.reset();
    }
});

async function onSubmit(event: Event): Promise<void> {
    event.preventDefault();
    await form.handleSubmit();
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="items-center text-center">
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-brand-gradient"
                >
                    <AppLogoIcon class="size-5 fill-current text-white" />
                </span>
                <DialogTitle>Welcome back</DialogTitle>
                <DialogDescription>
                    Sign in to pick up where you left off.
                </DialogDescription>
            </DialogHeader>

            <form novalidate class="grid gap-6" @submit="onSubmit">
                <FieldGroup>
                    <form.Field name="email" #default="{ field }">
                        <AppField
                            :field="field"
                            label="Email address"
                            required
                            #default="{ control }"
                        >
                            <Input
                                v-bind="control"
                                type="email"
                                autocomplete="email"
                                placeholder="you@company.com"
                                :model-value="field.state.value"
                                @blur="field.handleBlur"
                                @update:model-value="
                                    field.handleChange(String($event))
                                "
                            />
                        </AppField>
                    </form.Field>

                    <form.Field name="password" #default="{ field }">
                        <AppField
                            :field="field"
                            label="Password"
                            required
                            #default="{ control }"
                        >
                            <PasswordInput
                                v-bind="control"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                :model-value="field.state.value"
                                @blur="field.handleBlur"
                                @update:model-value="
                                    field.handleChange(String($event))
                                "
                            />
                        </AppField>
                    </form.Field>

                    <form.Field name="remember" #default="{ field }">
                        <div class="flex items-center justify-between gap-4">
                            <Label
                                class="flex items-center gap-2.5 font-normal"
                                :for="`${field.name}-remember`"
                            >
                                <Checkbox
                                    :id="`${field.name}-remember`"
                                    :name="field.name"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        field.handleChange(Boolean($event))
                                    "
                                />
                                Remember me
                            </Label>

                            <Link
                                :href="request()"
                                class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>
                    </form.Field>
                </FieldGroup>

                <Button
                    type="submit"
                    variant="shine"
                    class="mt-2 w-full"
                    :disabled="isSubmitting"
                    data-test="login-dialog-submit"
                >
                    <Spinner v-if="isSubmitting" />
                    Sign in
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
