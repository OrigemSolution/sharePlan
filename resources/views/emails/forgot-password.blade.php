<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
</head>
<body>
    <p>Hello,</p>
    <p>You have requested to reset your password. Use the OTP below to proceed:</p>
    <h2>{{ $otp }}</h2>
    <p>This OTP will expire in 10 minutes. If you didn't request a password reset, please ignore this email.</p>
    <p>Regards,<br/>{{ config('app.name') }} Team</p>
</body>
</html>