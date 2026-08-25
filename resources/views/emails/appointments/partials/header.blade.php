{{-- Expects $company (Shared\Infrastructure\Company\CompanyProfile::data()). --}}
<div class="header">
    @if (! empty($company['logo_url']))
        <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}">
    @endif
    <span class="brand-name">{{ $company['name'] }}</span>
</div>
