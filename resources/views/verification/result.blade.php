<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 460px;
            margin: 5vh auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
            text-align: center;
        }
        .status-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            font-size: 28px;
        }
        .status-icon.success {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }
        .status-icon.error {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
        }
        .headline {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .message {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            background: #2563eb;
            color: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-icon {{ $status === 'success' ? 'success' : 'error' }}">
            {{ $status === 'success' ? '✓' : '!' }}
        </div>
        <div class="headline">
            {{ $status === 'success' ? 'Email verified' : 'Verification issue' }}
        </div>
        <p class="message">{{ $message }}</p>
        <a class="cta" href="{{ config('app.frontend_login_url', '/school-fe-template/update/v10/login.html') }}">
            Go to Login
        </a>
    </div>
</body>
</html>
