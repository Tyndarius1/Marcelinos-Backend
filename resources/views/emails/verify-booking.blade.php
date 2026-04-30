<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your booking</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Confirm your booking to secure your reservation. Reference: {{ $booking->reference_number }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:0 12px 34px rgba(15, 23, 42, 0.08); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #dbeafe; background:linear-gradient(135deg, #f0fdf4 0%, #ecfeff 45%, #eff6ff 100%); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        @php
                                            $logoPath = public_path('brand-logo.png');
                                        @endphp
                                        <img src="{{ file_exists($logoPath) ? $message->embed($logoPath) : (config('app.url') . '/brand-logo.png') }}" alt="Marcelino's" width="60" style="display:block; height:auto; border:0; outline:none; text-decoration:none;">
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <div style="margin:0 0 6px;">
                                            <span style="display:inline-block; padding:4px 10px; border-radius:999px; border:1px solid #86efac; color:#166534; font-size:11px; line-height:16px; font-weight:700; letter-spacing:0.3px; text-transform:uppercase;">
                                                Pending verification
                                            </span>
                                        </div>
                                        <div style="font-size:16px; line-height:22px; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Confirm your booking
                                        </div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Reference {{ $booking->reference_number }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 8px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:22px; line-height:30px; font-family:'Playfair Display', Georgia, 'Times New Roman', serif; font-weight:600; color:#111827;">
                                Hi {{ $booking->guest?->full_name ?? 'Guest' }},
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Thank you for starting a reservation with <strong>Marcelino's Resort Hotel</strong>. Please confirm your booking to secure your reservation.
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                This link expires after a limited time for your security. If you did not request this, you can ignore this message.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px dashed #bfdbfe; border-radius:12px; background-color:#f8fafc; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:12px 14px; font-size:13px; line-height:20px; color:#334155; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="color:#0f172a;">Quick steps:</strong> 1) Verify booking now, 2) Open billing statement, 3) Settle your down payment.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Booking Details
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        @php
                                            $paymentMethod = (string) ($booking->payment_method ?? 'cash');
                                            $onlinePlan = (string) ($booking->online_payment_plan ?? '');
                                            $paymentTypeLabel = 'Cash';

                                            if ($paymentMethod === 'online') {
                                                if (preg_match('/^partial_([1-9]|[1-9][0-9])$/', $onlinePlan, $partialMatches) === 1) {
                                                    $paymentTypeLabel = 'Online (Partial '.($partialMatches[1] ?? '').'%)';
                                                } else {
                                                    $paymentTypeLabel = 'Online (Full)';
                                                }
                                            }
                                        @endphp
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; line-height:22px; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Reference</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ $booking->reference_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Check-in</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ optional($booking->check_in)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Check-out</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ optional($booking->check_out)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Payment Type</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ $paymentTypeLabel }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Total</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ number_format($booking->total_price, 2) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #fecaca; background:linear-gradient(135deg, #fff1f2 0%, #fffbeb 55%, #ffffff 100%); border-radius:14px; box-shadow:0 6px 16px rgba(239, 68, 68, 0.08); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <p style="margin:0 0 8px; font-size:12px; line-height:18px; color:#991b1b; font-weight:800; letter-spacing:0.4px; text-transform:uppercase; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Down payment notice
                                        </p>
                                        <p style="margin:0; font-size:13.5px; line-height:22px; color:#111827; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            A down payment is required to keep your reservation active. Please verify your booking, then open the billing statement to settle the required amount on time.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:2px 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #86efac; background:linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 45%, #f8fafc 100%); border-radius:14px; box-shadow:0 10px 24px rgba(22, 163, 74, 0.12); font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:16px 18px; text-align:center; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <p style="margin:0 0 10px; font-size:13px; line-height:20px; color:#15803d; font-weight:700; letter-spacing:0.3px; text-transform:uppercase; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Action required to activate booking
                                        </p>
                                        <p style="margin:0 0 14px; font-size:14px; line-height:22px; color:#1f2937; font-weight:500; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Tap the button below to verify now and finalize your reservation.
                                        </p>
                                        <table role="presentation" align="center" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius:11px; background:linear-gradient(135deg, #16a34a 0%, #166534 100%); box-shadow:0 10px 24px rgba(22, 163, 74, 0.35);">
                                                    <a href="{{ $verificationUrl }}" style="display:inline-block; padding:14px 26px; font-size:15px; line-height:20px; font-weight:700; letter-spacing:0.2px; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#ffffff; text-decoration:none; border-radius:11px;">
                                                        Verify & Confirm Booking
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:12px 0 0; font-size:12.5px; line-height:19px; color:#475569; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            This step is required. If not confirmed, your booking stays in pending verification.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Need help? Just reply to this email and we'll assist you.
                            </p>
                            <p style="margin:8px 0 0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                After confirming, you can view your billing statement here:
                                <a href="{{ rtrim(config('app.frontend_url'), '/') }}/billing/{{ $booking->id }}?token={{ urlencode($billingToken) }}" style="color:#15803d; font-weight:600; text-decoration:none; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    Billing statement
                                </a>
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
