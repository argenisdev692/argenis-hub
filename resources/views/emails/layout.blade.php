@php
    // Brand palette (inlined — email clients do not support CSS variables).
    // Mirrors the dark-mode token values in resources/css/globals.css, which are
    // themselves ported from BRAND/root.css. Keep the two in step: a change to
    // one without the other is exactly how an email starts looking off-brand.
    $cInk = '#050714';        // --background
    $cBase = '#08081f';       // --card
    $cSurface = '#131b3a';    // --popover / --secondary
    $cOverlay = '#1e2a4a';    // --border
    $cText = '#f4f4f2';       // --foreground
    $cSecondary = '#cbd2e0';  // between --foreground and --muted-foreground
    $cMuted = '#94a3b8';      // --muted-foreground
    $cBorder = 'rgba(255,255,255,0.10)';
    $cAccent = '#7c3aed';     // --brand-purple
    $cAccentSoft = '#a78bfa'; // --brand-purple-soft
    $gradient = 'linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%)'; // --gradient-tech
    // Inter matches the app shell (--font-sans). Mail clients rarely load web
    // fonts, so the stack degrades to the host's UI face rather than serif.
    $font = "'Inter', 'Segoe UI', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif";

    // DB-first company branding (App\Models\CompanyData), config/env fallback.
    $profile = \Shared\Infrastructure\Company\CompanyProfile::data();
    $company = $profile['name'];
    // White logo renders on the dark gradient header.
    $logo = $profile['logo_white_url'];
    $companyUrl = $profile['url'];
    $location = collect([
        $profile['city'] ?? null,
        $profile['state'] ?? null,
        $profile['country'] ?? null,
    ])->filter(static fn (?string $part): bool => $part !== null && $part !== '')->implode(', ');
    $support = $profile['support_email'];

    // Preheader: short inbox-preview snippet (child views may @section('preheader')).
    $preheader = trim($__env->yieldContent('preheader'));

    $socials = collect($profile['socials']);
    $socialMeta = [
        'linkedin'  => ['color' => '#0a66c2', 'label' => 'LinkedIn',  'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z'],
        'twitter'   => ['color' => '#1d9bf0', 'label' => 'Twitter/X', 'path' => 'M18.9 1.5h3.68l-8.04 9.19L24 22.5h-7.4l-5.8-7.58-6.64 7.58H.48l8.6-9.83L0 1.5h7.59l5.24 6.93L18.9 1.5zm-1.3 18.8h2.04L6.49 3.6H4.3L17.6 20.3z'],
        'instagram' => ['color' => '#e1306c', 'label' => 'Instagram', 'path' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.31-1.46.72-2.13 1.38C1.35 2.68.94 3.35.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.79.72 1.46 1.38 2.13.67.66 1.34 1.07 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.79-.31 1.46-.72 2.13-1.38.66-.67 1.07-1.34 1.38-2.13.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.31-.79-.72-1.46-1.38-2.13-.67-.66-1.34-1.07-2.13-1.38-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zm0 10.16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.41-10.4a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z'],
        'facebook'  => ['color' => '#1877f2', 'label' => 'Facebook',  'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.02 4.39 11.01 10.13 11.93v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8v8.44C19.61 23.08 24 18.09 24 12.07z'],
        'github'    => ['color' => '#24292f', 'label' => 'GitHub',    'path' => 'M12 .3a12 12 0 0 0-3.79 23.4c.6.11.82-.26.82-.58l-.01-2.05c-3.34.72-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.75.08-.73.08-.73 1.2.08 1.84 1.24 1.84 1.24 1.07 1.84 2.81 1.31 3.5 1 .11-.78.42-1.31.76-1.61-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.13-.3-.54-1.52.11-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6.01 0c2.29-1.55 3.3-1.23 3.3-1.23.65 1.66.24 2.88.12 3.18.77.84 1.23 1.91 1.23 3.22 0 4.61-2.8 5.63-5.48 5.92.43.37.82 1.1.82 2.22l-.01 3.29c0 .32.21.7.82.58A12 12 0 0 0 12 .3z'],
        'tiktok'    => ['color' => '#25f4ee', 'label' => 'TikTok',    'path' => 'M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $company }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Client resets */
        html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }
        * { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; border-collapse:collapse !important; }
        img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
        a { text-decoration:none; }
        a[x-apple-data-detectors] { color:inherit !important; text-decoration:none !important; }

        /* Mobile-first responsive tuning (~600px cap per 2026 best practice) */
        @media only screen and (max-width:620px) {
            .email-card { width:100% !important; border-radius:0 !important; }
            .email-pad { padding-left:22px !important; padding-right:22px !important; }
            .email-head { padding:24px 22px !important; }
            .email-h1 { font-size:20px !important; }
            .email-cta a { display:block !important; width:100% !important; box-sizing:border-box !important; text-align:center !important; }
        }

        /* Dark-mode chrome awareness (content stays a light "paper" for legibility) */
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color:#050714 !important; }
            .logo-glow { filter:drop-shadow(0 0 1px rgba(255,255,255,0.55)); }
        }
    </style>
</head>
<body class="email-bg" style="margin:0; padding:0; width:100%; background-color:{{ $cInk }}; font-family:{{ $font }}; -webkit-font-smoothing:antialiased;">
    {{-- Preheader: shown in the inbox preview, hidden in the body. --}}
    @if($preheader !== '')
        <div style="display:none; overflow:hidden; line-height:1px; max-height:0; max-width:0; opacity:0; mso-hide:all;">
            {{ $preheader }}&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;
        </div>
    @endif

    <table role="presentation" class="email-bg" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $cInk }}; padding:40px 12px;">
        <tr>
            <td align="center">
                <!--[if mso]>
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td>
                <![endif]-->
                <table role="presentation" class="email-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:{{ $cSurface }}; border:1px solid {{ $cBorder }}; border-radius:20px; overflow:hidden; box-shadow:0 24px 60px rgba(5,7,15,0.55);">

                    {{-- Header --}}
                    <tr>
                        <td class="email-head" style="background:{{ $cAccent }}; background:{{ $gradient }}; padding:34px 32px; text-align:center;">
                            <a href="{{ $companyUrl }}" style="text-decoration:none;">
                                @if($logo)
                                    <img src="{{ $logo }}" alt="{{ $company }}" height="42" class="logo-glow" style="height:42px; display:inline-block; border:0;">
                                @else
                                    <span style="color:#ffffff; font-size:25px; font-weight:700; letter-spacing:0.4px;">{{ $company }}</span>
                                @endif
                            </a>
                        </td>
                    </tr>

                    {{-- Body (light "paper" content area for maximum readability) --}}
                    <tr>
                        <td class="email-pad" style="background-color:#ffffff; padding:40px 36px; color:#374151; font-size:16px; line-height:1.65;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="email-pad" style="background-color:{{ $cBase }}; border-top:1px solid {{ $cBorder }}; padding:30px 32px; text-align:center;">
                            @if($socials->isNotEmpty())
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 20px;">
                                    <tr>
                                        @foreach($socials as $key => $url)
                                            @php $meta = $socialMeta[$key] ?? null; @endphp
                                            @if($meta)
                                                <td style="padding:0 6px;">
                                                    <a href="{{ $url }}" title="{{ $meta['label'] }}" style="display:inline-block; width:34px; height:34px; background-color:{{ $meta['color'] }}; border-radius:50%; text-align:center; line-height:34px;">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#ffffff" style="vertical-align:middle;" aria-hidden="true"><path d="{{ $meta['path'] }}"/></svg>
                                                    </a>
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:0 0 6px; color:{{ $cSecondary }}; font-size:13px; line-height:1.5;">
                                &copy; {{ date('Y') }} {{ $company }}. {{ __('All rights reserved.') }}
                            </p>
                            @if($location !== '')
                                <p style="margin:0 0 6px; color:{{ $cMuted }}; font-size:12px; line-height:1.5;">{{ $location }}</p>
                            @endif
                            <p style="margin:0; color:{{ $cMuted }}; font-size:12px; line-height:1.5;">
                                {{ __('Need help?') }} <a href="mailto:{{ $support }}" style="color:{{ $cAccentSoft }}; text-decoration:none; font-weight:600;">{{ $support }}</a>
                            </p>
                        </td>
                    </tr>

                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
