<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} password reset code</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="background:#0a0a0a;padding:24px 32px;">
                @php
                    $logoSrc = config('site.logo') ?: '/images/vireluxe/logo.png';
                    $logoSrc = preg_match('#^https?://#i', $logoSrc) ? $logoSrc : url($logoSrc);
                @endphp
                <img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" height="40" style="display:block;height:40px;width:auto;border:0;color:#d4af37;font-size:20px;font-weight:bold;">
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Use this security code to choose a new password for your {{ config('app.name') }} account.</p>
                <div style="margin:24px 0;text-align:center;">
                    <span style="display:inline-block;padding:14px 28px;background:#faf6e8;border:1px dashed #b8962e;border-radius:8px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#0a0a0a;">{{ $code }}</span>
                </div>
                <p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#52525b;">The code works for {{ $expiryMinutes }} minutes and only once.</p>
                <p style="margin:0;font-size:14px;line-height:1.5;color:#52525b;">If you did not ask for this, ignore this email. Your password stays the same.</p>
            </td>
        </tr>
    </table>
</body>
</html>
