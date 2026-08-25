@extends('emails.layout')

@section('preheader'){{ __('Activate your account and set your password.') }}@endsection

@section('content')
    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('Welcome!') }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('An administrator has created an account for you on :app.', ['app' => $company ?? config('app.name')]) }}
    </p>

    <p style="margin:0 0 24px; color:#4b5563;">
        {{ __('Click the button below to set your password and activate your account.') }}
    </p>

    {{-- CTA button (bulletproof: VML for Outlook, gradient for modern clients). --}}
    <table role="presentation" class="email-cta" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td align="center">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $activationUrl }}" style="height:50px;v-text-anchor:middle;width:240px;" arcsize="20%" strokecolor="#7c3aed" fillcolor="#7c3aed">
                    <w:anchorlock/>
                    <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;">{{ __('Activate account') }}</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-- -->
                <a href="{{ $activationUrl }}"
                   style="display:inline-block; padding:15px 34px; background-color:#7c3aed; background:linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%); color:#ffffff; font-size:16px; font-weight:700; text-decoration:none; border-radius:12px; box-shadow:0 8px 20px rgba(124,58,237,0.35);">
                    {{ __('Activate account') }}
                </a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('This invitation link expires in :hours hours.', ['hours' => $expiresInHours]) }}
    </p>

    {{-- Fallback for clients that strip the button. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 6px; color:#6b7280; font-size:13px;">{{ __('Or copy and paste this link into your browser:') }}</p>
                <a href="{{ $activationUrl }}" style="color:#7c3aed; font-size:13px; word-break:break-all; text-decoration:none;">{{ $activationUrl }}</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#6b7280; font-size:13px;">
        {{ __('If you were not expecting this invitation, you can safely ignore this email.') }}
    </p>
@endsection
