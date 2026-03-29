<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcelino's Contact Reply</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Response to your inquiry from Marcelino's Resort Hotel
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#ffffff; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">

                    <!-- Header -->
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <a href="{{ config('app.url') }}" style="text-decoration:none;">
                                            <img src="{{ config('app.url') . '/brand-logo.png' }}" alt="Marcelino's" width="60" style="display:block; height:auto; border:0; outline:none; text-decoration:none;">
                                        </a>
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <div style="font-size:16px; line-height:22px; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Inquiry Response
                                        </div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            {{ $contact->created_at->format('F j, Y') }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding:28px 32px 8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:22px; line-height:30px; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:600; color:#111827;">
                                Hello {{ $contact->full_name }},
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Thank you for reaching out to Marcelino's. We appreciate you taking the time to contact us regarding your inquiry.
                            </p>
                        </td>
                    </tr>

                    <!-- Inquiry Details -->
                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Your Original Inquiry
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; line-height:22px; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Subject
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ $contact->subject }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Date Submitted
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ $contact->created_at->format('F j, Y \\a\\t g:i A') }}
                                                </td>
                                            </tr>
                                            @if($contact->phone)
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Phone
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ $contact->phone }}
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; vertical-align:top; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Message
                                                </td>
                                                <td style="padding:6px 0; font-weight:400; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif; white-space:pre-line;">
                                                    {{ $contact->message }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Response -->
                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Our Response
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px; font-size:14px; line-height:24px; color:#374151; white-space:pre-line; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        {{ $replyMessage }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Contact Info / Note -->
                    <tr>
                        <td style="padding:0 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                If you have any additional questions or need further assistance, simply reply to this email and our team will be happy to help.
                            </p>

                            <p style="margin:0 0 16px; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                You may also visit our website for more information and updates.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" bgcolor="#749B66" style="border-radius:8px;">
                                        <a href="{{ config('app.url') }}/contact"
                                           style="display:inline-block; padding:12px 22px; font-size:14px; font-weight:600; line-height:20px; color:#ffffff; text-decoration:none; border-radius:8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Visit Our Website
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer Info -->
                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; line-height:20px; color:#6b7280; border-top:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <div style="font-weight:600; color:#374151; margin-bottom:6px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Marcelino's Team
                            </div>
                            <div style="margin-bottom:8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Website: <a href="{{ config('app.url') }}" style="color:#2563eb; text-decoration:none;">{{ config('app.url') }}</a>
                            </div>
                            <div style="margin-bottom:8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Email: {{ config('mail.from.address') }}                            
                            </div>
                            <div style="font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Thank you for choosing Marcelino's.
                            </div>
                        </td>
                    </tr>

                    <!-- Copyright -->
                    <tr>
                        <td style="padding:14px 32px 24px; font-size:11.5px; line-height:18px; color:#9ca3af; text-align:center; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            © {{ date('Y') }} Marcelino's. All rights reserved.<br>
                            This email was sent in response to your inquiry. If you did not submit this inquiry, please ignore this message.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>