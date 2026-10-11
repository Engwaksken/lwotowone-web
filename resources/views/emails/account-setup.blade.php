<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify your account</title></head>
<body style="margin:0;padding:0;background:#f3f6f3;font-family:Arial,Helvetica,sans-serif;color:#20352d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6f3;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #dce5df;border-radius:12px;overflow:hidden;">
            <tr><td style="padding:24px 32px;background:{{ $primaryColor }};text-align:center;">
                @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="display:block;max-width:210px;max-height:64px;width:auto;height:auto;margin:0 auto;">@else<strong style="color:#fff;font-size:19px;letter-spacing:1px;">{{ $brandName }}</strong>@endif
            </td></tr>
            <tr><td style="padding:34px 32px 18px;">
                <p style="margin:0 0 12px;color:{{ $primaryColor }};font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Account setup</p>
                <h1 style="margin:0 0 18px;font-size:25px;line-height:1.25;color:#20352d;">Verify your email address</h1>
                <p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Hello {{ $name }}, your Lwotowone account is ready. Verify your email to activate sign-in.</p>
                @if($temporaryPassword)
                <p style="margin:0 0 12px;font-size:15px;line-height:1.5;">Use these temporary login details after verifying:</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;background:#f4f7f4;border:1px solid #dce5df;border-radius:8px;">
                    <tr><td style="padding:14px 16px;font-size:14px;line-height:1.6;"><strong>Email:</strong> {{ $email }}<br><strong>Temporary password:</strong> <span style="font-family:Consolas,monospace;font-size:17px;letter-spacing:1px;">{{ $temporaryPassword }}</span></td></tr>
                </table>
                <p style="margin:0 0 22px;font-size:14px;line-height:1.6;">For your security, you must change this temporary password immediately after your first sign-in.</p>
                @endif
                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px auto 20px;"><tr><td align="center" style="border-radius:7px;background:{{ $primaryColor }};"><a href="{{ $verificationUrl }}" style="display:inline-block;padding:13px 24px;color:#fff;text-decoration:none;font-size:15px;font-weight:bold;">Verify email address</a></td></tr></table>
                <p style="margin:18px 0 0;color:#587066;font-size:13px;line-height:1.6;">If the button does not work, copy this link into your browser:<br><a href="{{ $verificationUrl }}" style="color:{{ $primaryColor }};word-break:break-all;">{{ $verificationUrl }}</a></p>
            </td></tr>
            <tr><td style="padding:18px 32px;border-top:1px solid #e5ebe6;color:#587066;font-size:12px;line-height:1.6;text-align:center;">{{ $brandName }} · Skills for Life. Enterprise for Livelihoods. Opportunities for Youth.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
