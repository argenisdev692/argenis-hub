@php
    $accent = '#7c3aed';
    $accentSoft = '#f5f3ff';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment rescheduled — {{ $company['name'] }}</title>
    @include('emails.appointments.partials.styles')
</head>
<body>
    <div class="wrapper">
        <div class="card">
            @include('emails.appointments.partials.header')

            <div class="content">
                <span class="eyebrow">Appointment Rescheduled</span>
                <h1>Your appointment moved, {{ $appointment->first_name }}</h1>

                <div class="banner">
                    Your appointment with <strong>{{ $company['name'] }}</strong> has been rescheduled to a new date and time.
                </div>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Status</td>
                            <td><span class="status-tag">Rescheduled</span></td>
                        </tr>
                        @if ($appointment->previous_scheduled_at)
                            <tr>
                                <td class="label">Previous</td>
                                <td style="color: #dc2626; text-decoration: line-through;">{{ $appointment->previous_scheduled_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="label">New Date &amp; Time</td>
                            <td style="color: #16a34a; font-weight: 700;">{{ $appointment->scheduled_at?->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                        </tr>
                        @if ($appointment->address)
                            <tr>
                                <td class="label">Address</td>
                                <td>
                                    {{ $appointment->address }}@if ($appointment->address_2), {{ $appointment->address_2 }}@endif
                                    @if ($appointment->city)<br>{{ $appointment->city }}@if ($appointment->state), {{ $appointment->state }}@endif @if ($appointment->zip_code) {{ $appointment->zip_code }}@endif @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>

                <p>Please confirm the new time works for you. If not, reply to this email or contact us using the details below.</p>
            </div>

            @include('emails.appointments.partials.footer')
        </div>
    </div>
</body>
</html>
