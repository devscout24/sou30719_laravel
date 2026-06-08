<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Password Reset</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f4f6f9; padding:30px">

    <div style="max-width:600px; margin:auto; background:#ffffff; padding:30px; border-radius:6px">

        <h2 style="color:#333">Reset Your Password</h2>

        <p>Hello {{ $user->name ?? 'User' }},</p>

        <p>
            You are receiving this email because we received a password reset request for your account.
        </p>

        <p style="margin:30px 0">
            <a href="{{ $resetLink }}"
                style="background:#0d6efd; color:#ffffff; padding:12px 20px; text-decoration:none; border-radius:4px;">
                Reset Password
            </a>
        </p>

        <p>
            This password reset link will expire in 60 minutes.
        </p>

        <p style="color:#888">
            If you did not request a password reset, no further action is required.
        </p>

        <hr>

        <p style="font-size:13px; color:#999">
            © {{ date('Y') }} Sunfix Admin
        </p>
    </div>

</body>

</html>
