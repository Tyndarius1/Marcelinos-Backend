<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Alert</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:'Poppins', Arial, Helvetica, sans-serif; color:#1f2937;">
    @php
        use App\Models\Booking;
        use App\Support\CancellationPolicy;

        $booking->loadMissing(['guest', 'payments']);
        $totalPaid = (float) $booking->total_paid;
        $totalPrice = (float) $booking->total_price;
        $overageRefund = max(0, $totalPaid - $totalPrice);
        $isCancelled = (string) $booking->booking_status === Booking::BOOKING_STATUS_CANCELLED;
        $cancellation = $isCancelled ? CancellationPolicy::breakdownForCancelledBooking($totalPrice, $totalPaid) : null;
        $appliesPercent = $cancellation !== null && ! empty($cancellation['applies_cancellation_percent']);
        $latestPayment = $booking->payments->sortByDesc('created_at')->first();
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6; padding:24px 0; margin:0; font-family:'Poppins', Arial, Helvetica, sans-serif;">
        <tr>
            <td align="center">
                <table role="presentation" width="700" cellspacing="0" cellpadding="0" style="width:700px; max-width:700px; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden; box-shadow:0 12px 34px rgba(15, 23, 42, 0.08);">
                    <tr>
                        <td style="padding:22px 28px; border-bottom:1px solid #dbeafe; background:linear-gradient(135deg, #fef2f2 0%, #fefce8 55%, #eff6ff 100%);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        @php($logoPath = public_path('brand-logo.png'))
                                        <img src="{{ file_exists($logoPath) ? $message->embed($logoPath) : (config('app.url') . '/brand-logo.png') }}" alt="Marcelino's" width="56" style="display:block; height:auto; border:0;">
                                    </td>
                                    <td style="vertical-align:middle; text-align:right;">
                                        <div style="font-size:16px; line-height:22px; font-weight:700; color:#111827;">Refund action required</div>
                                        <div style="font-size:12.5px; line-height:18px; color:#6b7280; font-weight:500;">Reference {{ $booking->reference_number }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 16px; line-height:1.6;">
                                Booking <strong>{{ $booking->reference_number }}</strong> is marked <strong>Refund pending</strong>. Process the guest refund externally, then mark the booking as refunded in admin when complete.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; border:1px solid #e5e7eb; border-radius:12px;">
                                <tr>
                                    <td colspan="2" style="padding:12px 16px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <strong style="font-size:14px; line-height:20px; color:#111827;">Refund summary</strong>
                                    </td>
                                </tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Booking reference</td><td style="padding:6px 0;"><strong>{{ $booking->reference_number }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->full_name ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest email</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->email ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total price</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPrice, 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total paid</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPaid, 2) }}</strong></td></tr>
                                @if($cancellation !== null && $appliesPercent)
                                <tr><td style="padding:6px 0; color:#6b7280;">Cancellation deduction</td><td style="padding:6px 0;"><strong>{{ (int) $cancellation['fee_percent'] }}% of booking total (PHP {{ number_format($cancellation['fee_from_total'], 2) }})</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount retained (fee)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_keep'], 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount to refund (policy)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_refund'], 2) }}</strong></td></tr>
                                @elseif($cancellation !== null)
                                <tr><td style="padding:6px 0; color:#6b7280;" colspan="2">Partial payment at cancellation: treated as a <strong>non-refundable reservation fee</strong>. No guest refund; retain PHP <strong>{{ number_format($cancellation['amount_to_keep'], 2) }}</strong>.</td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount to refund</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_refund'], 2) }}</strong></td></tr>
                                @else
                                <tr><td style="padding:6px 0; color:#6b7280;">Refund amount (paid minus new total)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($overageRefund, 2) }}</strong></td></tr>
                                @endif
                                <tr><td style="padding:6px 0; color:#6b7280;">Payment method</td><td style="padding:6px 0;"><strong>{{ $booking->payment_method ?: 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Provider ref</td><td style="padding:6px 0;"><strong>{{ $latestPayment?->provider_ref ?? 'N/A' }}</strong></td></tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-top:18px;">
                                <tr>
                                    <td align="center" style="border-radius:10px; background:linear-gradient(135deg, #d97706 0%, #92400e 100%);">
                                        <a href="{{ $bookingAdminUrl }}" style="display:inline-block; padding:12px 20px; font-size:14px; line-height:20px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Open booking in admin
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
