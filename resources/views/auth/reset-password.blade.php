<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 24px;
            color: #111827;
        }
        .card {
            max-width: 420px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 24px 24px 20px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }
        h1 {
            margin-top: 0;
            font-size: 1.35rem;
        }
        .field {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 4px;
            color: #374151;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.55rem 0.7rem;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 1px #2563eb22;
        }
        button {
            width: 100%;
            border: none;
            border-radius: 6px;
            padding: 0.6rem 1rem;
            background: linear-gradient(90deg, #f59e0b, #eab308);
            color: #111827;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
        }
        button:disabled {
            opacity: 0.6;
            cursor: default;
        }
        .message {
            margin-top: 12px;
            font-size: 0.9rem;
        }
        .message.error {
            color: #b91c1c;
        }
        .message.success {
            color: #15803d;
        }
        .helper {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reset your password</h1>
        <p style="font-size:0.9rem; color:#4b5563; margin-top:0; margin-bottom:16px;">
            Enter a new password for your account associated with <strong>{{ $email }}</strong>.
        </p>
        <form id="reset-form">
            <input type="hidden" id="email" value="{{ $email }}">
            <input type="hidden" id="token" value="{{ $token }}">

            <div class="field">
                <label for="password">New Password</label>
                <input id="password" type="password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            <button id="submit-btn" type="submit">Update Password</button>
            <div id="message" class="message" aria-live="polite"></div>
            <div class="helper">
                After a successful reset, you can close this tab and log in again.
            </div>
        </form>
    </div>

    <script>
        const RESET_REDIRECT_URL = {!! json_encode($redirectUrl ?? '') !!};

        (function () {
            const form = document.getElementById('reset-form');
            const messageEl = document.getElementById('message');
            const submitBtn = document.getElementById('submit-btn');

            function setMessage(text, kind) {
                messageEl.textContent = text || '';
                messageEl.className = 'message ' + (kind || '');
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                setMessage('', '');

                const email = (document.getElementById('email').value || '').trim();
                const token = (document.getElementById('token').value || '').trim();
                const password = (document.getElementById('password').value || '').trim();
                const passwordConfirmation = (document.getElementById('password_confirmation').value || '').trim();

                if (!password || !passwordConfirmation) {
                    setMessage('Please enter and confirm your new password.', 'error');
                    return;
                }
                if (password.length < 8) {
                    setMessage('Password must be at least 8 characters.', 'error');
                    return;
                }
                if (password !== passwordConfirmation) {
                    setMessage('Passwords do not match.', 'error');
                    return;
                }

                submitBtn.disabled = true;
                setMessage('Updating password...', '');

                fetch('/api/v1/password/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        token: token,
                        password: password,
                        password_confirmation: passwordConfirmation,
                    }),
                })
                    .then(async (response) => {
                        const contentType = response.headers.get('content-type') || '';
                        let payload = null;
                        if (contentType.includes('application/json')) {
                            try {
                                payload = await response.json();
                            } catch (_) {
                                payload = null;
                            }
                        }
                        if (!response.ok) {
                            const errorMessage =
                                (payload && payload.message) ||
                                'Unable to reset password. The link may be invalid or expired.';
                            throw new Error(errorMessage);
                        }

                        const successMessage =
                            (payload && payload.message) ||
                            'Password updated successfully. You can now log in with your new password.';
                        setMessage(successMessage, 'success');

                        if (RESET_REDIRECT_URL) {
                            setTimeout(function () {
                                window.location.href = RESET_REDIRECT_URL;
                            }, 2000);
                        }
                    })
                    .catch((error) => {
                        console.error('Password reset failed', error);
                        setMessage(error.message || 'Unable to reset password. Please try again later.', 'error');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                    });
            });
        })();
    </script>
</body>
</html>
