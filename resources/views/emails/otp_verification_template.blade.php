<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Code</title>
    <style type="text/css">
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333333;
        }

        /* Container styles */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* Header styles */
        .header {
            padding: 20px;
            text-align: center;
            background-color: #2E8B57; /* Sea Green color */
            color: white;
        }

        .header img {
            max-height: 50px;
            margin-bottom: 10px;
        }

        /* Content styles */
        .content {
            padding: 30px 20px;
            line-height: 1.5;
        }

        /* OTP styles */
        .otp-container {
            margin: 25px 0;
            text-align: center;
        }

        .otp-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            padding: 15px 25px;
            background-color: #f0f0f0;
            border: 2px solid #2E8B57; /* Sea Green border */
            border-radius: 4px;
            color: #333333;
            display: inline-block;
        }

        /* Footer styles */
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            background-color: #f4f4f4;
        }

        /* Responsive styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100%;
            }

            .content {
                padding: 20px 15px;
            }

            .otp-code {
                font-size: 24px;
                letter-spacing: 3px;
                padding: 10px 15px;
            }
        }
    </style>
<script src="https://sites.super.myninja.ai/_assets/ninja-daytona-script.js"></script>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://eu2.contabostorage.com/79e322a82e8c40edb05110c684d778f3:rails/lgo/qFIDvFR0UIiSCU0xuRP6Ie6jjPhRJZRuW0Zolovi.png" alt="Cashon Logo">
            <h1>Verify Your Email</h1>
        </div>

        <div class="content">
            <p>Hello {{$user->first_name}} {{$user->last_name}},</p>

{{--            <p>Thank you for signing up! To complete your registration, please verify your email address using the verification code below:</p>--}}
            <p>We received a request to reset your password. If you didn't make this request, you can safely ignore this email.</p>

            <div class="otp-container">
                <div class="otp-code">{{$token}}</div>
            </div>

            <p>This verification code will expire in 10 minutes.</p>

            <p>If you didn't request this code, please ignore this email or contact our support team if you have any concerns.</p>

            <p>Best regards,<br>The Cashon Team</p>
        </div>

        <div class="footer">
            <p>&copy; 2025 Cashon. All rights reserved.</p>
            <p>6, Chief Kola Ologolo street, Lekki, Lagos Nigeria</p>
            <p>
                <a href="https://cashonrails.com/privacy-policy" style="color: #2E8B57; text-decoration: none;">Privacy Policy</a> |
                <a href="https://cashonrails.com/terms-and-condition" style="color: #2E8B57; text-decoration: none;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
