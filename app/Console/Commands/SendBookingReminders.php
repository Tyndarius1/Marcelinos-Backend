<?php

namespace App\Console\Commands;

use App\Mail\BookingReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';
    protected $description = 'Send booking reminder emails one day before check-in at 12 noon';

    public function handle(): int
    {
        $now = now('Asia/Manila');
        $tomorrow = $now->copy()->addDay()->toDateString();

        $this->info("Checking bookings for reminder. Target check-in date: {$tomorrow}");

        $bookings = Booking::query()
            ->whereDate('check_in', $tomorrow)
            ->where('reminder_sent', false)
            ->whereIn('status', ['confirmed', 'paid'])
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
                if (!$booking->guest || !$booking->guest->email) {
                    Log::warning('Booking reminder skipped: missing guest email.', [
                        'booking_id' => $booking->id,
                    ]);
                    continue;
                }

                $this->info("Sending reminder to: {$booking->guest->email} for booking ID {$booking->id}");

                Mail::to($booking->guest->email)->send(new BookingReminderMail($booking));

                $booking->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => now(),
                ]);

                $sentCount++;

                Log::info('Booking reminder sent successfully.', [
                    'booking_id' => $booking->id,
                    'email' => $booking->guest->email,
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
}