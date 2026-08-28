@extends('emails.layout')

@section('preheader'){{ __('New contact request from :name — :subject', ['name' => $name, 'subject' => $subject]) }}@endsection

@section('content')
    @if($isSpam || $spamScore > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr>
                <td style="background-color:{{ $isSpam ? '#fef2f2' : '#fff7ed' }}; border:1px solid {{ $isSpam ? '#ef4444' : '#f59e0b' }}; border-radius:12px; padding:16px 18px;">
                    <span style="display:inline-block; color:{{ $isSpam ? '#b91c1c' : '#b45309' }}; font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                        &#9888;&nbsp; {{ $isSpam ? __('Flagged as spam') : __('Possible spam') }} &middot; {{ __('score :score/100', ['score' => $spamScore]) }}
                    </span>
                    @if(!empty($spamReasons))
                        <p style="margin:8px 0 0; color:#6b7280; font-size:12px; line-height:1.5;">
                            {{ __('Signals:') }} {{ implode(', ', $spamReasons) }}
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('New contact request') }}
    </h1>

    <p style="margin:0 0 24px; color:#4b5563;">
        {{ __(':name submitted the contact form on your site. Their details are below — reply to this email to answer them directly.', ['name' => $name]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; width:38%;">{{ __('Name') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right;">{{ $name }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('Email') }}</td>
            <td style="padding:12px 16px; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">
                <a href="mailto:{{ $submitterEmail }}" style="color:#7c3aed; text-decoration:none; font-weight:600;">{{ $submitterEmail }}</a>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('Phone') }}</td>
            <td style="padding:12px 16px; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">
                <a href="tel:{{ $phone }}" style="color:#7c3aed; text-decoration:none; font-weight:600;">{{ $phone }}</a>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('Subject') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $subject }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('SMS consent') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $smsConsent ? __('Yes') : __('No') }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px; color:#6b7280; font-size:13px; border-top:1px solid #eef0f4;">{{ __('Received') }}</td>
            <td style="padding:12px 16px; color:#111827; font-size:13px; text-align:right; border-top:1px solid #eef0f4;">{{ $submittedAt }}</td>
        </tr>
    </table>

    <p style="margin:0 0 8px; color:#6b7280; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">{{ __('Message') }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px; background-color:#ffffff; border:1px solid #e5e7eb; border-left:4px solid #7c3aed; border-radius:8px;">
        <tr>
            <td style="padding:16px 18px; color:#374151; font-size:14px; line-height:1.6; white-space:pre-wrap; word-break:break-word;">{{ $messageBody }}</td>
        </tr>
    </table>

    <table role="presentation" class="email-cta" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td align="center">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $inboxUrl }}" style="height:50px;v-text-anchor:middle;width:240px;" arcsize="20%" strokecolor="#7c3aed" fillcolor="#7c3aed">
                    <w:anchorlock/>
                    <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;">{{ __('Open the support inbox') }}</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-- -->
                <a href="{{ $inboxUrl }}"
                   style="display:inline-block; padding:15px 34px; background-color:#7c3aed; background:linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%); color:#ffffff; font-size:16px; font-weight:700; text-decoration:none; border-radius:12px; box-shadow:0 8px 20px rgba(124,58,237,0.35);">
                    {{ __('Open the support inbox') }}
                </a>
                <!--<![endif]-->
            </td>
        </tr>
    </table>
@endsection
