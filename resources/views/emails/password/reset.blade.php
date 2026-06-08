<!doctype html>
<html>

<body>
    <h2>Password Reset</h2>

    <p>Hello {{ $user->name ?? 'User' }},</p>

    <p>
        Click the link below to reset your password:
    </p>

    <p>
        <a href="{{ $resetLink }}">
            Reset Password
        </a>
    </p>

    <p>If you didn’t request this, please ignore this email.</p>
</body>

</html>
