@extends('emails.layout')

@section('preheader'){{ __('We noticed a sign-in from a new device or location.') }}@endsection

@section('content')
    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('New sign-in to your account') }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('We noticed a sign-in from a device or location we have not seen before.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px;">{{ __('IP address') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right;">{{ $ipAddress ?? __('unknown') }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('Device') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $userAgent ?? __('unknown') }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('When') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $occurredAt }}</td>
        </tr>
    </table>

    @isset($sessionsUrl)
        <table role="presentation" class="email-cta" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
            <tr>
                <td align="center">
                    <a href="{{ $sessionsUrl }}" style="display:inline-block; background:#7c3aed; background:linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%); color:#ffffff; font-size:15px; font-weight:700; padding:14px 28px; border-radius:10px; text-decoration:none;">
                        {{ __('Review active sessions') }}
                    </a>
                </td>
            </tr>
        </table>
    @endisset

    <p style="margin:0; color:#4b5563;">
        {{ __('If this was you, no action is needed. If not, change your password and review your active sessions.') }}
    </p>
@endsection
