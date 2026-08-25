{{--
    Shared inline <style> block for every appointment email. Hardcoded hex
    values mirror `resources/css/globals.css` (email clients cannot resolve
    CSS custom properties) — see BACKEND-PHP theme tokens:
      --text-primary #0b1020 · --text-secondary #3a3a52 · --text-muted #6a6a82
      --bg-app #f8f8fc · --bg-surface #ffffff · --bg-card #f1f1f6
      --border-default rgba(0,0,0,.1) · --radius-lg 12px
    `$accent` / `$accentSoft` are passed per-template (indigo/green/purple/red)
    to carry the status banner color; everything else is shared.
--}}
<style>
    body {
        margin: 0;
        padding: 0;
        background-color: #f8f8fc;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #0b1020;
    }

    .mono {
        font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, Menlo, monospace;
    }

    .wrapper {
        max-width: 600px;
        margin: 0 auto;
        padding: 32px 16px;
    }

    .card {
        background-color: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        overflow: hidden;
    }

    .header {
        padding: 28px 32px;
        text-align: center;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .header img {
        max-height: 40px;
        width: auto;
    }

    .header .brand-name {
        display: block;
        margin-top: 10px;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: .01em;
        color: #0b1020;
    }

    .content {
        padding: 32px;
        line-height: 1.6;
        font-size: 14px;
        color: #3a3a52;
    }

    .eyebrow {
        display: inline-block;
        font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, Menlo, monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: {{ $accent }};
    }

    h1 {
        margin: 6px 0 20px 0;
        font-size: 21px;
        font-weight: 700;
        color: #0b1020;
    }

    .banner {
        background-color: {{ $accentSoft }};
        border: 1px solid {{ $accent }}33;
        border-radius: 10px;
        padding: 16px 18px;
        margin: 0 0 22px 0;
        font-size: 14px;
        color: #0b1020;
    }

    .status-tag {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        background-color: {{ $accent }};
        color: #ffffff;
    }

    .details {
        background-color: #f1f1f6;
        border-radius: 10px;
        padding: 18px 20px;
        margin: 0 0 22px 0;
    }

    .details table {
        width: 100%;
        border-collapse: collapse;
    }

    .details td {
        padding: 6px 0;
        font-size: 13.5px;
        vertical-align: top;
    }

    .details td.label {
        width: 130px;
        color: #6a6a82;
        font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, Menlo, monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .button {
        display: inline-block;
        padding: 12px 22px;
        border-radius: 8px;
        background-color: {{ $accent }};
        color: #ffffff !important;
        text-decoration: none;
        font-weight: 700;
        font-size: 13.5px;
    }

    .footer {
        padding: 22px 32px 28px 32px;
        text-align: center;
        font-size: 12px;
        color: #6a6a82;
    }

    .footer a {
        color: #6a6a82;
        text-decoration: none;
    }

    .footer .contact-line {
        margin: 2px 0;
    }
</style>
