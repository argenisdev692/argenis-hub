@php
    $accent = '#7c3aed';
    $accentSoft = '#f5f0ff';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New lead — {{ $company['name'] }}</title>
    @include('emails.appointments.partials.styles')
</head>
<body>
    <div class="wrapper">
        <div class="card">
            @include('emails.appointments.partials.header')

            <div class="content">
                <span class="eyebrow">New Lead</span>
                <h1>A new lead just came in</h1>

                <div class="banner">
                    <strong>{{ $appointment->first_name }} {{ $appointment->last_name }}</strong> requested an appointment through the website. Reach out before someone else does.
                </div>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Name</td>
                            <td>{{ $appointment->first_name }} {{ $appointment->last_name }}</td>
                        </tr>
                        <tr>
                            <td class="label">Email</td>
                            <td><a href="mailto:{{ $appointment->email }}">{{ $appointment->email }}</a></td>
                        </tr>
                        @if ($appointment->phone)
                            <tr>
                                <td class="label">Phone</td>
                                <td><a href="tel:{{ $appointment->phone }}">{{ \Shared\Infrastructure\Support\PhoneFormatter::national($appointment->phone) }}</a></td>
                            </tr>
                        @endif
                        <tr>
                            <td class="label">Client Type</td>
                            <td>{{ ucfirst($appointment->client_type->value) }}</td>
                        </tr>
                        @if ($appointment->company_name)
                            <tr>
                                <td class="label">Company</td>
                                <td>{{ $appointment->company_name }}</td>
                            </tr>
                        @endif
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
                        @if ($appointment->scheduled_at)
                            <tr>
                                <td class="label">Requested</td>
                                <td>{{ $appointment->scheduled_at->locale('en')->isoFormat('dddd, MMMM D, YYYY [at] h:mm A') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="label">SMS Consent</td>
                            <td>{{ $appointment->sms_consent ? 'Yes' : 'No' }}</td>
                        </tr>
                        @if ($appointment->notes)
                            <tr>
                                <td class="label">Notes</td>
                                <td>{{ $appointment->notes }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                <p style="text-align: center; margin: 26px 0 4px 0;">
                    <a class="button" href="mailto:{{ $appointment->email }}">Reply to {{ $appointment->first_name }}</a>
                </p>
            </div>

            @include('emails.appointments.partials.footer')
        </div>
    </div>
</body>
</html>
