<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f9fafc;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .header {
            background-color: #f7a73a;
            color: #3d1273;
            padding: 20px 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
        }

        .content {
            padding: 30px;
            line-height: 1.6;
        }

        .content p {
            margin: 12px 0;
            font-size: 15px;
        }

        .otp-box {
            text-align: center;
            margin: 25px 0;
        }

        .otp-code {
            display: inline-block;
            background-color: #f0f4ff;
            color: #f7a73a;
            font-weight: bold;
            font-size: 26px;
            letter-spacing: 4px;
            padding: 12px 24px;
            border-radius: 8px;
            border: 1px solid #d0e0ff;
        }

        .footer {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: #777;
            border-top: 1px solid #eee;
        }

        .footer a {
            color: #f7a73a;
            text-decoration: none;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper {
                margin: 20px;
            }
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>

        <div class="content">
            <p>Hello,</p>
            <p>We received a request to reset your password for your <strong>{{ config('app.name') }}</strong> account.</p>
            <p>Please use the OTP below to complete your password reset:</p>

            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
            </div>

            <p>This OTP will expire in <strong>10 minutes</strong>. If you didn’t request this password reset, you can safely ignore this email.</p>

            <p>Thank you,<br>
            <strong>{{ config('app.name') }} Team</strong></p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
