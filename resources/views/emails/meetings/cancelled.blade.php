@extends('emails.layout')

@section('preheader'){{ __('A meeting you were invited to has been cancelled: :title', ['title' => $meeting->title]) }}@endsection

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td style="background-color:#fef2f2; border:1px solid #fca5a5; border-radius:12px; padding:16px 18px;">
                <span style="display:inline-block; color:#b91c1c; font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                    {{ __('Meeting cancelled') }}
                </span>
            </td>
        </tr>
    </table>

    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ $meeting->title }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('Hi :name, this meeting has been cancelled and no longer requires your attendance.', ['name' => $attendeeName]) }}
    </p>

    <p style="margin:0; color:#6b7280; font-size:13px;">
        {{ __('It was originally scheduled for :when.', ['when' => $meeting->starts_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A')]) }}
    </p>
@endsection
