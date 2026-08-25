{{-- Expects $company (Shared\Infrastructure\Company\CompanyProfile::data()). --}}
@php
    $location = collect([
        $company['city'] ?? null,
        $company['state'] ?? null,
        $company['country'] ?? null,
    ])->filter(static fn (?string $part): bool => $part !== null && $part !== '')->implode(', ');
@endphp
<div class="footer">
    @if (! empty($company['support_email']))
        <p class="contact-line"><a href="mailto:{{ $company['support_email'] }}">{{ $company['support_email'] }}</a></p>
    @endif
    @if ($location !== '')
        <p class="contact-line">{{ $location }}</p>
    @endif
    <p class="contact-line">&copy; {{ date('Y') }} {{ $company['name'] }}. All rights reserved.</p>
</div>
