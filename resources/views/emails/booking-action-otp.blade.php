<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcelino's Resort Hotel</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Verification email for your Marcelino's booking request.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#ffffff; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        @php($logoPath = public_path('brand-logo.png'))
                                        <img src="{{ file_exists($logoPath) ? $message->embed($logoPath) : (config('app.url') . '/brand-logo.png') }}" alt="Marcelino's" width="60" style="display:block; height:auto; border:0; outline:none; text-decoration:none;">
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <div style="font-size:16px; line-height:22px; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            {{ $purposeLabel }}
                                        </div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Verification code
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:22px; line-height:30px; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:600; color:#111827;">
                                Hi {{ $guestName }},
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Use this one-time code to verify your booking request. It expires in <strong style="color:#374151;">{{ $ttlMinutes }} minutes</strong>. Do not share it with anyone.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Your code
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:22px 18px; text-align:center; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <span style="display:inline-block; font-size:28px; letter-spacing:6px; font-weight:700; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            {{ $code }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                If you did not request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; line-height:20px; color:#6b7280; border-top:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <div style="font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Marcelino's Team
                            </div>
                            <div style="font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Thank you for choosing us.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
