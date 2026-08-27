import { z } from 'zod';

/**
 * Client-side shape for the landing page's login dialog.
 *
 * This is a UX affordance, not a security control: the server re-validates and
 * re-authenticates on every submit (see `routes/login`). Its only job is to
 * catch a typo before it costs a round-trip.
 *
 * The 6–10 character password bound mirrors the account policy so an obviously
 * wrong entry is caught before the round-trip; the server remains the source of
 * truth and rejects anything this misses.
 */
export const loginFormSchema = z.object({
    // Piped rather than chained so an empty field reads "Enter your email
    // address." instead of the format error.
    email: z
        .string()
        .trim()
        .min(1, 'Enter your email address.')
        .pipe(z.email('That does not look like an email address.')),
    // Piped so an empty field reads "Enter your password." rather than the
    // length hint.
    password: z
        .string()
        .min(1, 'Enter your password.')
        .pipe(
            z
                .string()
                .min(6, 'Password must be at least 6 characters.')
                .max(10, 'Password must be no more than 10 characters.'),
        ),
    remember: z.boolean(),
});

export type LoginFormValues = z.infer<typeof loginFormSchema>;

export const LOGIN_FORM_DEFAULTS: LoginFormValues = {
    email: '',
    password: '',
    remember: false,
};
