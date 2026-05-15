<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingGuestDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_guest_name_prefers_snapshot_over_linked_profile(): void
    {
        $guest = Guest::create([
            'first_name' => 'LK',
            'middle_name' => null,
            'last_name' => 'ROA',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
            'country' => 'Philippines',
            'region' => null,
            'province' => null,
            'municipality' => null,
            'barangay' => null,
        ]);

        $guest->update(['first_name' => 'LKS', 'last_name' => 'ROA']);

        $booking = Booking::create([
            'guest_id' => $guest->id,
            'guest_name_snapshot' => 'LK ROA',
            'guest_email_snapshot' => 'shared@example.com',
            'guest_contact_snapshot' => '09123456789',
            'check_in' => now()->addDay(),
            'check_out' => now()->addDays(2),
            'no_of_days' => 1,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);

        $this->assertSame('LK ROA', $booking->displayGuestName());
        $this->assertSame('LKS ROA', $guest->fresh()->full_name);
    }
}
