@php
    $accent = '#dc2626';
    $accentSoft = '#fef2f2';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment cancelled — {{ $company['name'] }}</title>
    @include('emails.appointments.partials.styles')
</head>
<body>
    <div class="wrapper">
        <div class="card">
            @include('emails.appointments.partials.header')

            <div class="content">
                <span class="eyebrow">Appointment Cancelled</span>
                <h1>Your appointment was cancelled</h1>

                <div class="banner">
                    We're sorry, {{ $appointment->first_name }} — your appointment with <strong>{{ $company['name'] }}</strong> has been cancelled.
                </div>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Status</td>
                            <td><span class="status-tag">Cancelled</span></td>
                        </tr>
                        @if ($appointment->scheduled_at)
                            <tr>
                                <td class="label">Was Scheduled</td>
                                <td>{{ $appointment->scheduled_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <p>Want to book a new time? Visit our website to schedule again, or reply to this email and we'll help you find a new slot.</p>

                @if (! empty($company['url']))
                    <p style="text-align: center; margin: 26px 0 4px 0;">
                        <a class="button" href="{{ $company['url'] }}">Book a new appointment</a>
                    </p>
                @endif
            </div>

            @include('emails.appointments.partials.footer')
        </div>
    </div>
</body>
</html>
