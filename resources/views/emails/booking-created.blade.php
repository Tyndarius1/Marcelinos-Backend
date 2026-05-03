<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcelino's Resort Hotel</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your booking has been received. Reference: {{ $booking->reference_number }}
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
                                        <div style="font-size:16px; line-height:22px; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Booking Confirmation
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
                                Hi {{ $guestDisplayName }},
                            </p>

                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Thanks for booking with us! Your reservation is now in our system. We'll follow up if we need anything else.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
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
                                                <td style="padding:6px 0; width:38%; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Reference
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ $booking->reference_number }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Check-in
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ optional($booking->check_in)->format('M d, Y h:i A') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Check-out
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ optional($booking->check_out)->format('M d, Y h:i A') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Payment Type
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ $paymentTypeLabel }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    Total
                                                </td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                    {{ number_format($booking->total_price, 2) }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @php
                        $bookingStatusLower = strtolower((string) ($booking->booking_status ?? ''));
                        $paymentStatusLower = strtolower((string) ($booking->payment_status ?? ''));
                        $showDownPaymentNotice = in_array($bookingStatusLower, ['reserved', 'rescheduled'], true)
                            && $paymentStatusLower === \App\Models\Booking::PAYMENT_STATUS_UNPAID;

                        $downPaymentPercent = 50;
                        $deadlineAt = now()->timezone('Asia/Manila')->setTime(21, 0, 0);
                        $depositDueLabel = $deadlineAt->format('F j, Y g:i A');
                    @endphp

                    @if ($showDownPaymentNotice)
                        <tr>
                            <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #fecaca; background:linear-gradient(135deg, #fff1f2 0%, #fffbeb 55%, #ffffff 100%); border-radius:14px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    <tr>
                                        <td style="padding:16px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <div style="margin:0 0 8px; font-size:12px; line-height:18px; color:#991b1b; font-weight:900; letter-spacing:0.6px; text-transform:uppercase; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                Action required — avoid cancellation
                                            </div>
                                            <div style="margin:0; font-size:16px; line-height:24px; color:#111827; font-weight:800; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                Down payment: <span style="color:#b91c1c;">{{ $downPaymentPercent }}%</span> before <span style="color:#b91c1c;">9:00 PM today</span>.
                                            </div>
                                            <div style="margin-top:8px; font-size:13.5px; line-height:22px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                If not paid by <strong>{{ $depositDueLabel }}</strong> (Philippine time), your booking will be <strong style="color:#b91c1c;">cancelled</strong>.
                                            </div>
                                            <div style="margin-top:10px; font-size:12.5px; line-height:20px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                Open your billing statement below for the exact amount and payment instructions.
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 32px 24px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <p style="margin:0 0 12px; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <a href="{{ rtrim(config('app.frontend_url'), '/') }}/billing/{{ $booking->id }}?token={{ urlencode($billingToken) }}" style="color:#15803d; font-weight:600; text-decoration:none; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    Click here to view your billing statement
                                </a>
                            </p>

                            <p style="margin:0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Need help? Just reply to this email and we'll assist you.
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