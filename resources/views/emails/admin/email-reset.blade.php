<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Email Change Verification</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6">

    <h2>Hello {{ $name }},</h2>

    <p>You requested to change your email.</p>

    <p><strong>OTP:</strong> {{ $otp }}</p>

    <p>
        Click the link below to confirm your email change:
    </p>

    <a href="{{ $link }}">
        Confirm Email Change
    </a>

    <p>This link will expire in 30 minutes.</p>


</body>

</html>
