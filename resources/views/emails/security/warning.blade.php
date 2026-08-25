@extends('emails.layout')

@section('preheader'){{ __('Security alert: multiple failed sign-in attempts detected.') }}@endsection

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td style="background-color:#fff7ed; border:1px solid #f59e0b; border-radius:12px; padding:16px 18px;">
                <span style="display:inline-block; color:#b45309; font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                    &#9888;&nbsp; {{ __('Security alert') }}
                </span>
            </td>
        </tr>
    </table>

    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('Multiple failed sign-in attempts') }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('We detected :attempts consecutive failed sign-in attempts on your account. As a precaution, it has been temporarily locked for :minutes minutes.', ['attempts' => $attempts, 'minutes' => $lockMinutes]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px;">{{ __('IP address') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right;">{{ $ipAddress ?? __('unknown') }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('When') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $occurredAt }}</td>
        </tr>
    </table>

    <p style="margin:0 0 24px; color:#4b5563;">
        {{ __('If this was you, you can sign in again once the lock expires. If you did not attempt to sign in, please reset your password immediately.') }}
    </p>

    @if(!empty($resetUrl))
        <table role="presentation" class="email-cta" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center">
                    <!--[if mso]>
                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $resetUrl }}" style="height:48px;v-text-anchor:middle;width:220px;" arcsize="20%" strokecolor="#7c3aed" fillcolor="#7c3aed">
                        <w:anchorlock/>
                        <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:bold;">{{ __('Reset my password') }}</center>
                    </v:roundrect>
                    <![endif]-->
                    <!--[if !mso]><!-- -->
                    <a href="{{ $resetUrl }}" style="display:inline-block; padding:14px 28px; background-color:#7c3aed; background:linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%); color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; border-radius:12px; box-shadow:0 8px 20px rgba(124,58,237,0.35);">
                        {{ __('Reset my password') }}
                    </a>
                    <!--<![endif]-->
                </td>
            </tr>
        </table>
    @endif
@endsection
