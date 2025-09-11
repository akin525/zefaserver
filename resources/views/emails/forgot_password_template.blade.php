<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
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

        /* Button styles */
        .button {
            display: inline-block;
            padding: 12px 24px;
            margin: 20px 0;
            background-color: #2E8B57; /* Sea Green color */
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
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
        }
    </style>
<script src="https://sites.super.myninja.ai/_assets/ninja-daytona-script.js"></script>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://eu2.contabostorage.com/79e322a82e8c40edb05110c684d778f3:rails/lgo/qFIDvFR0UIiSCU0xuRP6Ie6jjPhRJZRuW0Zolovi.png" alt="Cashon Logo">
            <h1>Password Reset Request</h1>
        </div>

        <div class="content">
            <p>Hello {{$user->first_name}},</p>

            <p>We received a request to reset your password. If you didn't make this request, you can safely ignore this email.</p>

            <p>To reset your password, click the button below:</p>

            <div style="text-align: center;">
                <a href="{{$resetUrl}}" >Reset Password</a>
            </div>

            <p>This link will expire in 24 hours for security reasons.</p>

            <p>If the button above doesn't work, copy and paste the following URL into your browser:</p>

            <p style="word-break: break-all; font-size: 12px;">{{$resetUrl}}</p>

            <p>If you need further assistance, please contact our support team.</p>

            <p>Best regards,<br>The Cashon Team</p>
        </div>

        <div class="footer">
            <p>&copy; 2025 Cashon. All rights reserved.</p>
{{--            <p>123 Company Street, City, Country</p>--}}
            <p>
                <a href="[PRIVACY_POLICY_LINK]" style="color: #2E8B57; text-decoration: none;">Privacy Policy</a> |
                <a href="[TERMS_OF_SERVICE_LINK]" style="color: #2E8B57; text-decoration: none;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
