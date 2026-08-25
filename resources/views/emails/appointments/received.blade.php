@php
    $accent = '#0ea5e9';
    $accentSoft = '#e0f2fe';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment request received — {{ $company['name'] }}</title>
    @include('emails.appointments.partials.styles')
</head>
<body>
    <div class="wrapper">
        <div class="card">
            @include('emails.appointments.partials.header')

            <div class="content">
                <span class="eyebrow">Request Received</span>
                <h1>Thanks, {{ $appointment->first_name }}!</h1>

                <div class="banner">
                    We've received your appointment request and our team at <strong>{{ $company['name'] }}</strong> will review it shortly. You'll get a confirmation email once it's booked.
                </div>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Status</td>
                            <td><span class="status-tag">Pending confirmation</span></td>
                        </tr>
                        @if ($appointment->scheduled_at)
                            <tr>
                                <td class="label">Requested Date &amp; Time</td>
                                <td>{{ $appointment->scheduled_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                            </tr>
                        @endif
                        @include('emails.appointments.partials.service-row')
                        @if ($appointment->notes)
                            <tr>
                                <td class="label">Notes</td>
                                <td>{{ $appointment->notes }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <p>We'll be in touch soon. If anything changes in the meantime, just reply to this email and we'll help you out.</p>
            </div>

            @include('emails.appointments.partials.footer')
        </div>
    </div>
</body>
</html>
