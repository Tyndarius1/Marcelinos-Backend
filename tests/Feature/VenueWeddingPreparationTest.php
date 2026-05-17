<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Venue;
use App\Support\BookingPricing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueWeddingPreparationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        config()->set('services.api.key', 'test-api-key');

        return [
            'x-api-key' => 'test-api-key',
            'Accept' => 'application/json',
        ];
    }

    public function test_wedding_blocks_prep_day_on_same_venue(): void
    {
        $guest = $this->createGuest();
        $v1 = $this->createVenue('Wedding prep A');
        $v2 = $this->createVenue('Wedding prep B');

        $b = $this->createWeddingBooking($guest, $v1, '2026-05-02 00:00:00', '2026-05-04 00:00:00');

        $this->assertNotNull($b->id);

        $checkIn = Carbon::parse('2026-05-01 00:00:00');
        $checkOut = Carbon::parse('2026-05-02 23:59:59');

        $ids = Venue::query()->whereIn('id', [$v1->id, $v2->id])
            ->availableBetween(
                $checkIn,
                $checkOut,
                null,
                BookingPricing::VENUE_EVENT_WEDDING,
                true,
            )
            ->pluck('id')
            ->all();

        $this->assertContains($v2->id, $ids);
        $this->assertNotContains($v1->id, $ids);
    }

    public function test_birthday_booking_does_not_reserve_day_before_check_in_for_other_wedding(): void
    {
        $guest = $this->createGuest();
        $v1 = $this->createVenue('Birthday A');

        $this->createBirthdayBooking($guest, $v1, '2026-06-10 00:00:00', '2026-06-12 00:00:00');

        $checkIn = Carbon::parse('2026-06-03 00:00:00');
        $checkOut = Carbon::parse('2026-06-04 23:59:59');

        $ids = Venue::query()->whereKey($v1->id)
            ->availableBetween(
                $checkIn,
                $checkOut,
                null,
                BookingPricing::VENUE_EVENT_WEDDING,
                true,
            )
            ->pluck('id')
            ->all();

        $this->assertContains($v1->id, $ids);
    }

    public function test_null_venue_event_type_still_blocks_overlapping_venue(): void
    {
        $guest = $this->createGuest();
        $v1 = $this->createVenue('Null event type A');

        $b = Booking::withoutEvents(fn () => Booking::query()->create([
            'guest_id' => $guest->id,
            'reference_number' => 'TEST-NE-'.strtoupper(Str::random(8)),
            'receipt_token' => (string) Str::uuid(),
            'check_in' => '2026-07-10 12:00:00',
            'check_out' => '2026-07-11 10:00:00',
            'no_of_days' => 1,
            'total_price' => 5000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'venue_event_type' => null,
        ]));
        $b->venues()->attach($v1->id);

        $checkIn = Carbon::parse('2026-07-10 00:00:00');
        $checkOut = Carbon::parse('2026-07-10 23:59:59');

        $ids = Venue::query()->whereKey($v1->id)
            ->availableBetween(
                $checkIn,
                $checkOut,
                null,
                BookingPricing::VENUE_EVENT_WEDDING,
                true,
            )
            ->pluck('id')
            ->all();

        $this->assertNotContains($v1->id, $ids);

        $q = http_build_query([
            'check_in' => '2026-07-10T00:00:00.000Z',
            'check_out' => '2026-07-10T23:59:59.000Z',
            'venue_event_type' => 'wedding',
        ]);

        $res = $this->getJson('/api/venues?'.$q, $this->apiHeaders());
        $res->assertStatus(200);
        $row = collect($res->json('data'))->firstWhere('id', $v1->id);
        $this->assertIsArray($row);
        $this->assertFalse($row['available']);
    }

    public function test_get_venues_marks_wedding_prep_conflict(): void
    {
        $guest = $this->createGuest();
        $v1 = $this->createVenue('API prep A');
        $this->createWeddingBooking($guest, $v1, '2026-05-02 00:00:00', '2026-05-04 00:00:00');

        $q = http_build_query([
            'check_in' => '2026-05-01T00:00:00.000Z',
            'check_out' => '2026-05-02T23:59:59.000Z',
            'venue_event_type' => 'wedding',
        ]);

        $res = $this->getJson('/api/venues?'.$q, $this->apiHeaders());
        $res->assertStatus(200);
        $data = $res->json('data');
        $this->assertIsArray($data);
        $row = collect($data)->firstWhere('id', $v1->id);
        $this->assertIsArray($row);
        $this->assertFalse($row['available']);
    }

    private function createGuest(): Guest
    {
        return Guest::query()->create([
            'first_name' => 'T',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09170000000',
            'email' => 'wprep+'.Str::lower(Str::random(8)).'@example.com',
            'gender' => 'male',
            'is_international' => false,
            'country' => 'Philippines',
        ]);
    }

    private function createVenue(string $name): Venue
    {
        return Venue::query()->create([
            'name' => $name,
            'description' => 'Test',
            'capacity' => 50,
            'wedding_price' => 5000,
            'birthday_price' => 5000,
            'meeting_staff_price' => 5000,
            'status' => Venue::STATUS_AVAILABLE,
        ]);
    }

    private function createWeddingBooking(Guest $guest, Venue $venue, string $checkIn, string $checkOut): Booking
    {
        $b = Booking::withoutEvents(fn () => Booking::query()->create([
            'guest_id' => $guest->id,
            'reference_number' => 'TEST-WP-'.strtoupper(Str::random(8)),
            'receipt_token' => (string) Str::uuid(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => 2,
            'total_price' => 5000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]));
        $b->venues()->attach($venue->id);

        return $b;
    }

    private function createBirthdayBooking(Guest $guest, Venue $venue, string $checkIn, string $checkOut): Booking
    {
        $b = Booking::withoutEvents(fn () => Booking::query()->create([
            'guest_id' => $guest->id,
            'reference_number' => 'TEST-BD-'.strtoupper(Str::random(8)),
            'receipt_token' => (string) Str::uuid(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => 2,
            'total_price' => 5000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'venue_event_type' => BookingPricing::VENUE_EVENT_BIRTHDAY,
        ]));
        $b->venues()->attach($venue->id);

        return $b;
    }
}
