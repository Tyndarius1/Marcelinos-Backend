<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Account Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Staff verification code for secure sign in.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:560px; max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:0 12px 34px rgba(15, 23, 42, 0.08); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #dbeafe; background:linear-gradient(135deg, #f0fdf4 0%, #ecfeff 45%, #eff6ff 100%); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        @php($logoPath = public_path('brand-logo.png'))
                                        <img src="{{ file_exists($logoPath) ? $message->embed($logoPath) : (config('app.url') . '/brand-logo.png') }}" alt="Marcelino's" width="60" style="display:block; height:auto; border:0;">
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <div style="font-size:16px; line-height:22px; font-weight:700;">Staff OTP Verification</div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500;">Secure access code</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 10px;">
                            <p style="margin:0 0 12px; font-size:22px; line-height:30px; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:600; color:#111827;">
                                Hello,
                            </p>
                            <p style="margin:0; color:#4b5563; font-size:14.5px; line-height:24px;">
                                To continue accessing your staff account, use this one-time verification code:
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 18px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600;">Your code</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 18px; text-align:center;">
                                        <span style="display:inline-block; font-size:30px; letter-spacing:8px; font-weight:700; color:#111827;">
                                            {{ $code }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0 0 10px; font-size:13.5px; line-height:22px; color:#6b7280;">
                                This code expires in <strong>10 minutes</strong>. Do not share it with anyone.
                            </p>
                            <p style="margin:0; font-size:13.5px; line-height:22px; color:#6b7280;">
                                If you did not request this verification, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; line-height:20px; color:#6b7280; border-top:1px solid #e5e7eb;">
                            <div style="font-weight:600; color:#374151;">Marcelino's Team</div>
                            <div>Thank you for helping us keep accounts secure.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>