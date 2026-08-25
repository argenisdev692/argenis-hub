import { router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { send } from '@/routes/auth/two-factor/email-code';

/** Matches `auth-security.otp.resend.per_minute` — one send per minute. */
const RESEND_COOLDOWN_SECONDS = 60;

export type UseEmailedCodeReturn = {
    /** True while a send request is in flight. */
    sending: Ref<boolean>;
    /** True once a code has been sent at least once this visit. */
    sent: Ref<boolean>;
    /** Seconds left before another send is allowed. */
    cooldown: Ref<number>;
    canSend: ComputedRef<boolean>;
    requestCode: () => void;
};

/**
 * Requests an emailed second-factor code and holds the resend cooldown.
 *
 * The cooldown mirrors the server's rate limit rather than being decorative:
 * without it the button invites a click that can only come back as a 429.
 * `useIntervalFn` is tied to the component scope, so the timer is torn down
 * with the component and cannot outlive the page.
 */
export function useEmailedCode(): UseEmailedCodeReturn {
    const sending = ref(false);
    const sent = ref(false);
    const cooldown = ref(0);

    const { pause, resume } = useIntervalFn(
        () => {
            cooldown.value -= 1;

            if (cooldown.value <= 0) {
                cooldown.value = 0;
                pause();
            }
        },
        1000,
        { immediate: false },
    );

    const canSend = computed(() => !sending.value && cooldown.value === 0);

    function requestCode(): void {
        if (!canSend.value) {
            return;
        }

        sending.value = true;

        router.post(
            send().url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    sent.value = true;
                    cooldown.value = RESEND_COOLDOWN_SECONDS;
                    resume();
                },
                onFinish: () => {
                    sending.value = false;
                },
            },
        );
    }

    return { sending, sent, cooldown, canSend, requestCode };
}
