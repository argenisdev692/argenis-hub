<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { KeyRound, Mail, MailCheck, Send, Smartphone } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import OtpCodeField from '@/modules/auth/components/OtpCodeField.vue';
import { useEmailedCode } from '@/modules/auth/composables/useEmailedCode';
import { verify as verifyEmailCode } from '@/routes/auth/two-factor/email-code';
import { store } from '@/routes/two-factor/login';

/**
 * The second-factor challenge, with all three routes to the same outcome.
 *
 * An authenticator app is first because it is the strongest and the fastest.
 * An emailed code sits second so a lost phone does not force the user to spend
 * a recovery code, which is single-use and easy to run out of. Recovery stays
 * last: it is the break-glass option, not a convenience.
 */
type ChallengeMethod = 'app' | 'email' | 'recovery';

const METHOD_COPY: Readonly<
    Record<ChallengeMethod, { title: string; description: string }>
> = {
    app: {
        title: 'Authentication code',
        description:
            'Enter the 6-digit code from your authenticator app — Google Authenticator, 1Password, Authy or any other.',
    },
    email: {
        title: 'Emailed code',
        description:
            'We can send a 6-digit code to the email address on your account.',
    },
    recovery: {
        title: 'Recovery code',
        description:
            'Use one of the emergency codes you saved when you turned on two-factor authentication.',
    },
} as const;

const method = ref<ChallengeMethod>('app');

const appCode = ref('');
const emailCode = ref('');

const { sending, sent, cooldown, canSend, requestCode } = useEmailedCode();

const page = usePage();

/** The controller's `with('status')` flash, shown after a code is sent. */
const status = computed(() => page.props.status as string | undefined);

watchEffect(() => {
    setLayoutProps(METHOD_COPY[method.value]);
});

function switchMethod(next: string): void {
    method.value = next as ChallengeMethod;
    appCode.value = '';
    emailCode.value = '';
}
</script>

<template>
    <Head title="Two-factor authentication" />

    <Tabs
        :model-value="method"
        class="w-full gap-6"
        @update:model-value="(value) => switchMethod(String(value))"
    >
        <TabsList class="grid w-full grid-cols-3">
            <TabsTrigger value="app">
                <Smartphone />
                <span class="hidden sm:inline">App</span>
            </TabsTrigger>
            <TabsTrigger value="email">
                <Mail />
                <span class="hidden sm:inline">Email</span>
            </TabsTrigger>
            <TabsTrigger value="recovery">
                <KeyRound />
                <span class="hidden sm:inline">Recovery</span>
            </TabsTrigger>
        </TabsList>

        <!-- Authenticator app (TOTP) — Fortify's own endpoint. -->
        <TabsContent value="app">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing }"
                @error="appCode = ''"
            >
                <input type="hidden" name="code" :value="appCode" />

                <OtpCodeField
                    v-model="appCode"
                    label="Authentication code"
                    :error="errors.code"
                    :disabled="processing"
                    autofocus
                />

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Continue
                </Button>
            </Form>
        </TabsContent>

        <!-- Emailed code — the module's own endpoint, same OTP machinery as
             email verification and password reset. -->
        <TabsContent value="email">
            <div class="space-y-4">
                <p
                    v-if="status"
                    class="flex items-center justify-center gap-2 rounded-lg border border-success/25 bg-success/8 px-3 py-2 text-sm text-success"
                    role="status"
                >
                    <MailCheck class="size-4" aria-hidden="true" />
                    {{ status }}
                </p>

                <Form
                    v-bind="verifyEmailCode.form()"
                    class="space-y-4"
                    reset-on-error
                    #default="{ errors, processing }"
                    @error="emailCode = ''"
                >
                    <input type="hidden" name="code" :value="emailCode" />

                    <OtpCodeField
                        v-model="emailCode"
                        label="Emailed code"
                        :error="errors.code"
                        :disabled="processing || !sent"
                    />

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing || !sent"
                    >
                        <Spinner v-if="processing" />
                        Continue
                    </Button>
                </Form>

                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    :disabled="!canSend"
                    @click="requestCode"
                >
                    <Spinner v-if="sending" />
                    <Send v-else aria-hidden="true" />
                    <template v-if="cooldown > 0">
                        Resend in
                        <span class="tabular-nums">{{ cooldown }}s</span>
                    </template>
                    <template v-else>
                        {{ sent ? 'Send a new code' : 'Email me a code' }}
                    </template>
                </Button>
            </div>
        </TabsContent>

        <!-- Recovery code — Fortify's endpoint again, different field. -->
        <TabsContent value="recovery">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing }"
            >
                <div class="space-y-2">
                    <Label for="recovery_code">Recovery code</Label>
                    <Input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        autocomplete="one-time-code"
                        placeholder="xxxxxxxxxx-xxxxxxxxxx"
                        :aria-invalid="Boolean(errors.recovery_code)"
                        required
                    />
                    <InputError :message="errors.recovery_code" />
                </div>

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Continue
                </Button>
            </Form>
        </TabsContent>
    </Tabs>
</template>
