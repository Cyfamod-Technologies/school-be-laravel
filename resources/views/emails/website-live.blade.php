<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your website is live</title>
</head>
<body style="font-family: Arial, sans-serif; background-color:#f6f6f6; padding:24px; color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background-color:#ffffff; border-radius:8px; padding:32px;">
        <tr>
            <td>
                <h2 style="margin-top:0; color:#111827;">Good news, {{ $school->name }}!</h2>
                <p style="line-height:1.5; margin-bottom:16px;">
                    Your school's website is now live and reachable by the public.
                </p>
                <p style="text-align:center; margin:32px 0;">
                    <a href="https://{{ $school->custom_domain }}" style="background-color:#16a34a; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
                        Visit your website
                    </a>
                </p>
                <p style="line-height:1.5; margin-bottom:16px;">
                    You can also manage its content anytime from Website Management in your admin dashboard.
                </p>
                <p style="margin-top:32px; color:#6b7280; font-size:14px;">
                    &mdash; The {{ config('app.name') }} Team
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
