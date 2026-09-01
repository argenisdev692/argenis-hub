{{-- Expects $company (Shared\Infrastructure\Company\CompanyProfile::data()). --}}
<div class="header">
    {{-- PNG rendition, not `logo_url`: Outlook for Windows has no WebP decoder. --}}
    @if (! empty($company['logo_email_url']))
        <img src="{{ $company['logo_email_url'] }}" alt="{{ $company['name'] }}">
    @endif
    <span class="brand-name">{{ $company['name'] }}</span>
</div>
