<script setup lang="ts">
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';

/**
 * The 6-digit entry shared by both code-based factors.
 *
 * One component so the slot count, the label wiring and the error placement
 * cannot drift between the authenticator tab and the email tab.
 */
const {
    label,
    error,
    disabled = false,
    autofocus = false,
} = defineProps<{
    label: string;
    error?: string;
    disabled?: boolean;
    autofocus?: boolean;
}>();

const code = defineModel<string>({ default: '' });

const fieldId = useId();
const errorId = useId();
</script>

<template>
    <div class="flex flex-col items-center gap-3 text-center">
        <label :for="fieldId" class="sr-only">{{ label }}</label>

        <InputOTP
            :id="fieldId"
            v-model="code"
            :maxlength="6"
            :disabled="disabled"
            :autofocus="autofocus"
            :aria-describedby="error ? errorId : undefined"
            :aria-invalid="Boolean(error)"
            inputmode="numeric"
            autocomplete="one-time-code"
        >
            <InputOTPGroup>
                <InputOTPSlot
                    v-for="index in 6"
                    :key="index"
                    :index="index - 1"
                />
            </InputOTPGroup>
        </InputOTP>

        <InputError :id="errorId" :message="error" />
    </div>
</template>
