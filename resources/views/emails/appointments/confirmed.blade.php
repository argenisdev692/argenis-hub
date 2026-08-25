@php
    $accent = '#16a34a';
    $accentSoft = '#ecfdf3';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment confirmed — {{ $company['name'] }}</title>
    @include('emails.appointments.partials.styles')
</head>
<body>
    <div class="wrapper">
        <div class="card">
            @include('emails.appointments.partials.header')

            <div class="content">
                <span class="eyebrow">Appointment Confirmed</span>
                <h1>You're all set, {{ $appointment->first_name }}!</h1>

                <div class="banner">
                    Your appointment with <strong>{{ $company['name'] }}</strong> has been confirmed. We look forward to speaking with you.
                </div>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Status</td>
                            <td><span class="status-tag">Confirmed</span></td>
                        </tr>
                        <tr>
                            <td class="label">Date &amp; Time</td>
                            <td>{{ $appointment->scheduled_at?->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                        </tr>
                        @include('emails.appointments.partials.service-row')
                        @if ($appointment->address)
                            <tr>
                                <td class="label">Address</td>
                                <td>
                                    {{ $appointment->address }}@if ($appointment->address_2), {{ $appointment->address_2 }}@endif
                                    @if ($appointment->city)<br>{{ $appointment->city }}@if ($appointment->state), {{ $appointment->state }}@endif @if ($appointment->zip_code) {{ $appointment->zip_code }}@endif @endif
                                </td>
                            </tr>
                        @endif
                        @if ($appointment->notes)
                            <tr>
                                <td class="label">Notes</td>
                                <td>{{ $appointment->notes }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <p>Need to make a change? Just reply to this email or reach out using the details below and we'll take care of it.</p>
            </div>

            @include('emails.appointments.partials.footer')
        </div>
    </div>
</body>
</html>
