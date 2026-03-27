<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Staff Account Verification</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f6f8;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:20px 0;">
        <tr>
            <td align="center">
                <table width="500" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.05);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background:#749B66; padding:20px; text-align:center; color:#ffffff;">
                            <h2 style="margin:0;">OTP Verification</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <p style="font-size:16px; margin-top:0;">
                                Hello,
                            </p>

                            <p style="font-size:16px;">
                                To continue accessing your staff account, please use the verification code below:
                            </p>

                            <div style="text-align:center; margin:30px 0;">
                                <span style="display:inline-block; font-size:28px; letter-spacing:4px; font-weight:bold; color:#2c3e50; background:#f1f3f5; padding:15px 25px; border-radius:6px;">
                                    {{ $code }}
                                </span>
                            </div>

                            <p style="font-size:14px; color:#555;">
                                This code will expire in <strong>10 minutes</strong>. For your security, do not share this code with anyone.
                            </p>

                            <p style="font-size:14px; color:#555;">
                                If you did not request this verification, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f1f3f5; padding:15px; text-align:center; font-size:12px; color:#888;">
                            © {{ date('Y') }} Your Company. All rights reserved.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>