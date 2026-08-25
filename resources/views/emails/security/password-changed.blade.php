@extends('emails.layout')

@section('preheader'){{ __('Confirmation that your account password was changed.') }}@endsection

@section('content')
    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('Your password was changed') }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('This is a confirmation that the password for your account was just changed.') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px;">{{ __('IP address') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right;">{{ $ipAddress ?? __('unknown') }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('When') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $changedAt ?? '' }}</td>
        </tr>
    </table>

    <p style="margin:0; color:#4b5563;">
        {{ __('If you did not make this change, reset your password immediately and contact support.') }}
    </p>
@endsection
