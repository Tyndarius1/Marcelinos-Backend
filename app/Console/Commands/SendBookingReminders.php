<?php

namespace App\Console\Commands;

use App\Mail\BookingReminderMail;
use App\Models\Booking;
use App\Services\SemaphoreSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';
    protected $description = 'Send booking reminder emails one day before check-in at 12 noon';

    public function handle(SemaphoreSmsService $smsService): int
    {
        $now = now('Asia/Manila');
        $tomorrow = $now->copy()->addDay()->toDateString();

        $this->info("Checking bookings for reminder. Target check-in date: {$tomorrow}");

        $bookings = Booking::query()
            ->whereDate('check_in', $tomorrow)
            ->where('reminder_sent', false)
            ->whereIn('booking_status', [
                Booking::BOOKING_STATUS_RESERVED,
                Booking::BOOKING_STATUS_RESCHEDULED,
            ])
            ->whereHas('guest', function ($query) {
                $query->whereNotNull('email');
            })
            ->with(['guest', 'rooms', 'venues'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No booking reminders to send.');
            Log::info('Booking reminder scheduler: no bookings found.', [
                'target_check_in' => $tomorrow,
            ]);

            return self::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($bookings as $booking) {
            try {
                if (! $booking->guest || ! $booking->guest->email) {
                    Log::warning('Booking reminder skipped: missing guest email.', [
                        'booking_id' => $booking->id,
                    ]);
                    continue;
                }

                $this->info("Sending reminder to: {$booking->guest->email} for booking ID {$booking->id}");

                $emailSent = false;
                $smsSent = false;
                $smsSentTo = null;
                $smsError = null;

                try {
                    $mail = Mail::to($booking->guest->email);
                    $bookingCcAddress = config('mail.booking_cc_address');

                    if (filled($bookingCcAddress)) {
                        $mail->cc($bookingCcAddress);
                    }

                    $billingToken = $booking->generateBillingAccessToken();
                    $mail->send(new BookingReminderMail($booking, $billingToken));
                    $emailSent = true;
                } catch (\Throwable $emailException) {
                    Log::error('Booking reminder email failed to send.', [
                        'booking_id' => $booking->id,
                        'email' => $booking->guest->email,
                        'error' => $emailException->getMessage(),
                    ]);
                }

                $contactNumber = (string) ($booking->guest->contact_num ?? '');
                if (trim($contactNumber) !== '') {
                    try {
                        $smsSentTo = $smsService->sendBookingReminder($contactNumber, $this->buildReminderSms($booking));
                        $smsSent = true;
                    } catch (\Throwable $smsException) {
                        $smsError = $smsException->getMessage();

                        Log::warning('Booking reminder SMS failed to send.', [
                            'booking_id' => $booking->id,
                            'contact_num' => $booking->guest->contact_num,
                            'error' => $smsError,
                        ]);
                    }
                } else {
                    $smsError = 'Missing guest contact number.';
                    Log::warning('Booking reminder SMS skipped: missing guest contact number.', [
                        'booking_id' => $booking->id,
                    ]);
                }

                $booking->update([
                    'reminder_sent' => $emailSent,
                    'reminder_sent_at' => $emailSent ? now() : null,
                    'reminder_sms_sent' => $smsSent,
                    'reminder_sms_sent_at' => $smsSent ? now() : null,
                    'reminder_sms_error' => $smsError,
                ]);

                if ($emailSent) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }

                Log::info('Booking reminder dispatch result.', [
                    'booking_id' => $booking->id,
                    'email' => $booking->guest->email,
                    'email_sent' => $emailSent,
                    'sms_sent' => $smsSent,
                    'sms_sent_to' => $smsSentTo,
                    'sms_error' => $smsError,
                    'check_in' => $booking->check_in,
                ]);
            } catch (\Throwable $e) {
                $failedCount++;

                Log::error('Booking reminder failed to send.', [
                    'booking_id' => $booking->id,
                    'email' => $booking->guest->email ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Booking reminders complete. Sent: {$sentCount}, Failed: {$failedCount}");

        return self::SUCCESS;
    }

    private function buildReminderSms(Booking $booking): string
    {
        $name = $booking->displayGuestName() !== '—' ? $booking->displayGuestName() : 'Guest';
        $checkIn = $booking->check_in?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'tomorrow';
        $reference = trim((string) $booking->reference_number);

        return "Hi {$name}, this is a reminder that your Marcelino's booking ({$reference}) starts on {$checkIn}. See you soon!";
    }
}