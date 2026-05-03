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
        {{ $preheader }}
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
                                            @if($refundAmount > 0.009)
                                                Refund completed
                                            @else
                                                Payment update
                                            @endif
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

                            @if($isCancelled)
                                <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    This confirms that we have <strong>updated your payment status to Refunded</strong> for booking <strong>{{ $booking->reference_number }}</strong> following your cancellation, and our records for this transaction are now closed on our side.
                                </p>
                            @else
                                <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    This confirms that we have <strong>updated your payment status to Refunded</strong> for booking <strong>{{ $booking->reference_number }}</strong> after your stay dates were <strong>rescheduled</strong>, and your payment has been fully reconciled in our system.
                                </p>
                            @endif

                            @if($refundAmount > 0.009)
                                <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    The amount to be returned to you is <strong>PHP {{ number_format($refundAmount, 2) }}</strong> @if($isCancelled) per our <strong>cancellation policy</strong> (see summary below). @else based on the difference between what you had already paid and your <strong>new booking total</strong> after the change. @endif
                                    Refunds are sent back through the <strong>same payment channel</strong> you used (for example, your original online payment or bank transfer arrangement). Timing can depend on your bank or card issuer—typically a few business days after we process the refund on our end.
                                </p>
                            @else
                                <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    <strong>No separate cash refund is due</strong> for this update: your payments and the current booking total are already aligned in our records @if($isCancelled) under the cancellation terms @else with your new dates @endif (see the summary below for amounts).
                                </p>
                                @if(!$isCancelled && $balanceDue > 0.009)
                                    <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px; line-height:24px; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        There remains an <strong>outstanding balance of PHP {{ number_format($balanceDue, 2) }}</strong> on this booking. Please settle it as agreed (your receipt link below shows the latest details).
                                    </p>
                                @endif
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Booking details
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; line-height:22px; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Reference</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ $booking->reference_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Stay status</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ $isCancelled ? 'Cancelled' : 'Rescheduled' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Check-in</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ optional($booking->check_in)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Check-out</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ optional($booking->check_out)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827; font-weight:600; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            Payment summary
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; line-height:22px; color:#374151; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <tr>
                                                <td style="padding:6px 0; width:45%; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Current booking total</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($totalPrice, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Total amount paid</td>
                                                <td style="padding:6px 0; font-weight:600; color:#374151; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($totalPaid, 2) }}</td>
                                            </tr>
                                            @if($cancellationBreakdown !== null && !empty($cancellationBreakdown['applies_cancellation_percent']))
                                                <tr>
                                                    <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Cancellation fee ({{ (int) $cancellationBreakdown['fee_percent'] }}% of booking total)</td>
                                                    <td style="padding:6px 0; font-weight:600; color:#374151; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($cancellationBreakdown['fee_from_total'], 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Amount retained (policy)</td>
                                                    <td style="padding:6px 0; font-weight:600; color:#374151; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($cancellationBreakdown['amount_to_keep'], 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:10px 0 6px; color:#111827; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">Amount refunded to you</td>
                                                    <td style="padding:10px 0 6px; font-weight:700; color:#111827; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">PHP {{ number_format($refundAmount, 2) }}</td>
                                                </tr>
                                            @elseif($cancellationBreakdown !== null)
                                                <tr>
                                                    <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif; vertical-align:top;">Reservation fee (not refundable)</td>
                                                    <td style="padding:6px 0; font-weight:600; color:#374151; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($cancellationBreakdown['amount_to_keep'], 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" style="padding:6px 0; font-size:12.5px; line-height:20px; color:#4b5563; font-family:'Poppins', Arial, Helvetica, sans-serif;">{{ $cancellationBreakdown['statement_note'] ?? '' }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:10px 0 6px; color:#111827; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">Amount refunded to you</td>
                                                    <td style="padding:10px 0 6px; font-weight:700; color:#111827; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">PHP {{ number_format($refundAmount, 2) }}</td>
                                                </tr>
                                            @else
                                                @if($balanceDue > 0.009)
                                                    <tr>
                                                        <td style="padding:6px 0; color:#6b7280; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">Balance still due</td>
                                                        <td style="padding:6px 0; font-weight:600; color:#b45309; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif;">PHP {{ number_format($balanceDue, 2) }}</td>
                                                    </tr>
                                                @endif
                                                <tr>
                                                    <td style="padding:10px 0 6px; color:#111827; font-weight:700; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">Refund due (if any)</td>
                                                    <td style="padding:10px 0 6px; font-weight:700; color:#111827; text-align:right; font-family:'Poppins', Arial, Helvetica, sans-serif; border-top:1px solid #e5e7eb;">PHP {{ number_format($refundAmount, 2) }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($refundAmount > 0.009)
                        <tr>
                            <td style="padding:0 32px 16px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #bbf7d0; background-color:#f0fdf4; border-radius:12px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                    <tr>
                                        <td style="padding:16px 18px; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                            <p style="margin:0 0 8px; font-size:13px; line-height:20px; color:#15803d; font-weight:700; letter-spacing:0.3px; text-transform:uppercase; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                What happens next
                                            </p>
                                            <p style="margin:0; font-size:14px; line-height:22px; color:#1f2937; font-weight:400; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                                You should see <strong>PHP {{ number_format($refundAmount, 2) }}</strong> returned to your original payment method. If you do not see it after several business days, or if the amount does not match your expectations, reply to this email and our team will look into it.
                                            </p>
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
                                    View your billing statement
                                </a>
                                for the latest charges, dates, and payment notes.
                            </p>
                            <p style="margin:0; font-size:13.5px; line-height:22px; color:#6b7280; font-family:'Poppins', Arial, Helvetica, sans-serif;">
                                Questions about this update? <strong>Reply to this email</strong> and we will be glad to help.
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
