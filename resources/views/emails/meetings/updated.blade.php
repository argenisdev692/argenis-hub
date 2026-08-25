@extends('emails.layout')

@section('preheader'){{ __('A meeting you are attending was updated: :title', ['title' => $meeting->title]) }}@endsection

@section('content')
    <h1 class="email-h1" style="margin:0 0 16px; color:#0b1020; font-size:22px; font-weight:700; line-height:1.3;">
        {{ __('Meeting updated') }}
    </h1>

    <p style="margin:0 0 16px; color:#4b5563;">
        {{ __('Hi :name, a meeting you are attending has been updated. Here are the current details:', ['name' => $attendeeName]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 10px; color:#111827; font-size:16px; font-weight:700;">{{ $meeting->title }}</p>
                <p style="margin:0 0 4px; color:#6b7280; font-size:13px;">
                    {{ __('When') }}: {{ $meeting->starts_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}
                    – {{ $meeting->ends_at->locale('en')->isoFormat('h:mm A') }}
                </p>
                @if ($meeting->description)
                    <p style="margin:12px 0 0; color:#374151; font-size:14px;">{{ $meeting->description }}</p>
                @endif
            </td>
        </tr>
    </table>

    @include('emails.meetings.partials.meet-link', ['meeting' => $meeting])
@endsection
