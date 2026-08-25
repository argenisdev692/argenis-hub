import { z } from 'zod';

/**
 * Client-side shape for the landing page's login dialog.
 *
 * This is a UX affordance, not a security control: the server re-validates and
 * re-authenticates on every submit (see `routes/login`). Its only job is to
 * catch a typo before it costs a round-trip, so the rules stay deliberately
 * loose — password *format* is never asserted here, because guessing the
 * server's policy would lock a valid user out of their own account.
 */
export const loginFormSchema = z.object({
    // Piped rather than chained so an empty field reads "Enter your email
    // address." instead of the format error.
    email: z
        .string()
        .trim()
        .min(1, 'Enter your email address.')
        .pipe(z.email('That does not look like an email address.')),
    password: z.string().min(1, 'Enter your password.'),
    remember: z.boolean(),
});

export type LoginFormValues = z.infer<typeof loginFormSchema>;

export const LOGIN_FORM_DEFAULTS: LoginFormValues = {
    email: '',
    password: '',
    remember: false,
};
