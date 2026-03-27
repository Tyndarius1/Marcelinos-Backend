<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reminder</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Friendly reminder: your booking at Marcelino's Resort and Hotel is scheduled for tomorrow.
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#ffffff; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                    
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <a href="{{ config('app.url') }}" style="text-decoration:none;">
                                            <img src="{{ config('app.url') . '/brand-logo.png' }}" alt="Marcelino's Logo" width="80" style="display:block; height:auto; border:0; outline:none; text-decoration:none;">
                                        </a>
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <div style="font-size:16px; line-height:22px; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Booking Reminder
                                        </div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Scheduled for tomorrow
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:22px; line-height:30px; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:600; color:#111827;">
                                Hello {{ $booking->full_name ?? $booking->guest_name }},
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                This is a friendly reminder that your booking at <strong>Marcelino's Resort and Hotel</strong> is scheduled for <strong>tomorrow</strong>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Booking Details
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; line-height:22px; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#6b7280; font-weight:400;">Check-in Date</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151;">
                                                    {{ \Carbon\Carbon::parse($booking->check_in)->format('F j, Y') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400;">Check-out Date</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151;">
                                                    {{ \Carbon\Carbon::parse($booking->check_out)->format('F j, Y') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400;">Reference No.</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151;">
                                                    {{ $booking->reference_no ?? $booking->id }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; color:#4b5563; font-size:14.5px; line-height:24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Please make sure to prepare the following before your arrival:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:4px 0; font-size:14px; line-height:22px; color:#374151;">• Valid ID</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; font-size:14px; line-height:22px; color:#374151;">• Booking reference</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; font-size:14px; line-height:22px; color:#374151;">• Any remaining payment, if applicable</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#2563eb;">
                                        <a href="{{ config('app.url') . '/booking' }}" style="display:inline-block; padding:12px 20px; font-size:14px; line-height:20px; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#ffffff; text-decoration:none; border-radius:8px;">
                                            View Booking
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                If you have questions or need assistance, just reply to this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; line-height:20px; color:#6b7280; border-top:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <div style="font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                {{ config('app.name') }}
                            </div>
                            <div style="margin-top:4px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                © {{ date('Y') }} Marcelino's Resort and Hotel. All rights reserved.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>