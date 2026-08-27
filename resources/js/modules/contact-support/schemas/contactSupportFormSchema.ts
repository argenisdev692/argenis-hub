import { z } from 'zod';
import type { ContactSupport, ContactSupportWritePayload } from '../types';

/**
 * The client-side mirror of `StoreContactSupportRequest` /
 * `UpdateContactSupportRequest`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. `TextField` always
 * hands back a string, so every text field is modelled as one and trimmed at
 * the wire boundary in `toWritePayload()`.
 */

const PHONE_PATTERN = /^\+?[0-9]{7,15}$/;

export const contactSupportFormSchema = z.object({
    first_name: z
        .string()
        .trim()
        .min(1, 'First name is required.')
        .max(255, 'First name must be 255 characters or fewer.'),
    last_name: z
        .string()
        .trim()
        .min(1, 'Last name is required.')
        .max(255, 'Last name must be 255 characters or fewer.'),
    email: z
        .email('Enter a valid email address.')
        .trim()
        .max(255, 'Email must be 255 characters or fewer.'),
    phone: z
        .string()
        .trim()
        .min(1, 'Phone is required.')
        .regex(
            PHONE_PATTERN,
            'Enter 7–15 digits, optionally starting with “+”.',
        ),
    subject: z
        .string()
        .trim()
        .min(1, 'Subject is required.')
        .max(150, 'Subject must be 150 characters or fewer.'),
    message: z
        .string()
        .trim()
        .min(1, 'Message is required.')
        .max(5000, 'Message must be 5000 characters or fewer.'),
    sms_consent: z.boolean(),
    readed: z.boolean(),
    is_spam: z.boolean(),
});

export type ContactSupportFormValues = z.infer<typeof contactSupportFormSchema>;

export function emptyContactSupportFormValues(): ContactSupportFormValues {
    return {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
        sms_consent: false,
        readed: false,
        is_spam: false,
    };
}

export function toContactSupportFormValues(
    support: ContactSupport,
): ContactSupportFormValues {
    return {
        first_name: support.first_name,
        last_name: support.last_name,
        email: support.email,
        phone: support.phone,
        subject: support.subject,
        message: support.message,
        sms_consent: support.sms_consent,
        readed: support.readed,
        is_spam: support.is_spam,
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * generated write payload: a missing or misnamed key is a compile error here
 * instead of a silently dropped column at runtime.
 */
export function toWritePayload(
    values: ContactSupportFormValues,
): ContactSupportWritePayload {
    return {
        first_name: values.first_name.trim(),
        last_name: values.last_name.trim(),
        email: values.email.trim(),
        phone: values.phone.trim(),
        subject: values.subject.trim(),
        message: values.message.trim(),
        sms_consent: values.sms_consent,
        readed: values.readed,
        is_spam: values.is_spam,
    };
}
