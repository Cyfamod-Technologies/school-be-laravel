<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify your email</title>
</head>
<body style="font-family: Arial, sans-serif; background-color:#f6f6f6; padding:24px; color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background-color:#ffffff; border-radius:8px; padding:32px;">
        <tr>
            <td>
                <h2 style="margin-top:0; color:#111827;">Hi {{ $user->name }},</h2>
                <p style="line-height:1.5; margin-bottom:16px;">
                    Thanks for registering your school on {{ config('app.name') }}.
                    Please verify your email address to activate your account.
                </p>
                <p style="text-align:center; margin:32px 0;">
                    <a href="{{ $verificationUrl }}" style="background-color:#2563eb; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
                        Verify Email Address
                    </a>
                </p>
                <p style="line-height:1.5; margin-bottom:16px;">
                    This link expires on <strong>{{ $expiresAt->setTimezone(config('app.timezone'))->format('M j, Y g:ia T') }}</strong>.
                    If the button above does not work, copy and paste the URL below into your browser:
                </p>
                <p style="word-break:break-all; background-color:#f3f4f6; padding:12px; border-radius:4px; font-size:14px;">
                    <a href="{{ $verificationUrl }}" style="color:#2563eb;">{{ $verificationUrl }}</a>
                </p>
                <p style="line-height:1.5; margin-top:24px;">
                    If you did not initiate this request, you can safely ignore this email.
                </p>
                <p style="margin-top:32px; color:#6b7280; font-size:14px;">
                    &mdash; The {{ config('app.name') }} Team
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
