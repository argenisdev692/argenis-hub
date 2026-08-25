@if ($meeting->meet_link)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
            <td class="email-cta" style="text-align:center;">
                <a href="{{ $meeting->meet_link }}"
                   style="display:inline-block; padding:14px 28px; background:linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%); color:#ffffff; font-size:15px; font-weight:700; border-radius:10px; text-decoration:none;">
                    {{ __('Join Google Meet') }}
                </a>
            </td>
        </tr>
        <tr>
            <td style="padding-top:10px; text-align:center;">
                <a href="{{ $meeting->meet_link }}" style="color:#6b7280; font-size:12px; word-break:break-all;">
                    {{ $meeting->meet_link }}
                </a>
            </td>
        </tr>
    </table>
@endif
