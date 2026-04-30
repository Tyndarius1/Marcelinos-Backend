<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\RoomCalendar;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RoomCalendarReservationFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_filter_switch_updates_calendar_without_refresh(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        try {
            $guest = Guest::query()->create([
                'first_name' => 'Test',
                'middle_name' => null,
                'last_name' => 'Guest',
                'contact_num' => '09170000000',
                'email' => 'calendar-filter@example.com',
                'gender' => 'male',
                'is_international' => false,
                'country' => 'Philippines',
            ]);

            $room = Room::query()->create([
                'name' => 'Test Standard 101',
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1200,
                'status' => Room::STATUS_AVAILABLE,
            ]);

            $venue = Venue::query()->create([
                'name' => 'Sky Garden Test Venue',
                'description' => 'Test venue',
                'capacity' => 80,
                'price' => 8000,
                'wedding_price' => 8000,
                'birthday_price' => 8000,
                'meeting_staff_price' => 8000,
                'status' => Venue::STATUS_AVAILABLE,
            ]);

            $roomOnly = $this->createBookingQuietly($guest, '2026-04-22 14:00:00', '2026-04-23 12:00:00');
            $roomOnly->rooms()->attach($room->id);

            $venueOnly = $this->createBookingQuietly($guest, '2026-04-22 14:00:00', '2026-04-23 12:00:00');
            $venueOnly->venues()->attach($venue->id);

            $roomAndVenue = $this->createBookingQuietly($guest, '2026-04-22 14:00:00', '2026-04-23 12:00:00');
            $roomAndVenue->rooms()->attach($room->id);
            $roomAndVenue->venues()->attach($venue->id);

            $component = Livewire::test(RoomCalendar::class)
                ->set('month', 4)
                ->set('year', 2026);

            $this->assertCount(3, $component->instance()->activeBookingRows);

            $component->assertSee('Standard');
            $component->assertDontSee($venue->name);
            $this->assertSame(1, $this->cellTypeCount($component->instance()->calendarWeeks, '2026-04-22', Room::TYPE_STANDARD));

            $component->set('reservationFilter', RoomCalendar::RESERVATION_VENUE);
            $this->assertCount(3, $component->instance()->activeBookingRows);
            $component->assertSee($venue->name);
            $component->assertDontSee('Standard');
            $this->assertSame(
                1,
                $this->cellTypeCount($component->instance()->calendarWeeks, '2026-04-22', (string) $venue->id)
            );

            $component->set('reservationFilter', RoomCalendar::RESERVATION_BOTH);
            $this->assertCount(3, $component->instance()->activeBookingRows);
            $component->assertDontSee($venue->name);
            $component->assertSee('Standard');
            $this->assertSame(1, $this->cellTypeCount($component->instance()->calendarWeeks, '2026-04-22', Room::TYPE_STANDARD));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_blocked_dates_are_marked_in_calendar_weeks_by_reservation_filter(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        try {
            $room = Room::query()->create([
                'name' => 'Test Family 201',
                'capacity' => 4,
                'type' => Room::TYPE_FAMILY,
                'price' => 2400,
                'status' => Room::STATUS_AVAILABLE,
            ]);

            $venue = Venue::query()->create([
                'name' => 'Sunset Hall',
                'description' => 'Test venue',
                'capacity' => 120,
                'price' => 12000,
                'wedding_price' => 12000,
                'birthday_price' => 12000,
                'meeting_staff_price' => 12000,
                'status' => Venue::STATUS_AVAILABLE,
            ]);

            BlockedDate::query()->create([
                'date' => '2026-04-24',
                'reason' => 'General maintenance',
            ]);

            RoomBlockedDate::query()->create([
                'room_id' => $room->id,
                'blocked_on' => '2026-04-25',
                'reason' => 'Room blocked by staff',
            ]);

            VenueBlockedDate::query()->create([
                'venue_id' => $venue->id,
                'blocked_on' => '2026-04-26',
                'reason' => 'Venue blocked by admin',
            ]);

            $roomComponent = Livewire::test(RoomCalendar::class)
                ->set('month', 4)
                ->set('year', 2026)
                ->set('reservationFilter', RoomCalendar::RESERVATION_ROOM);

            $this->assertTrue($this->cellIsBlocked($roomComponent->instance()->calendarWeeks, '2026-04-24'));
            $this->assertTrue($this->cellIsBlocked($roomComponent->instance()->calendarWeeks, '2026-04-25'));
            $this->assertFalse($this->cellIsBlocked($roomComponent->instance()->calendarWeeks, '2026-04-26'));

            $venueComponent = Livewire::test(RoomCalendar::class)
                ->set('month', 4)
                ->set('year', 2026)
                ->set('reservationFilter', RoomCalendar::RESERVATION_VENUE);

            $this->assertTrue($this->cellIsBlocked($venueComponent->instance()->calendarWeeks, '2026-04-24'));
            $this->assertFalse($this->cellIsBlocked($venueComponent->instance()->calendarWeeks, '2026-04-25'));
            $this->assertTrue($this->cellIsBlocked($venueComponent->instance()->calendarWeeks, '2026-04-26'));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createBookingQuietly(Guest $guest, string $checkIn, string $checkOut): Booking
    {
        return Booking::withoutEvents(fn () => Booking::query()->create([
            'guest_id' => $guest->id,
            'reference_number' => 'TEST-CAL-'.Str::upper(Str::random(8)),
            'receipt_token' => (string) Str::uuid(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => 1,
            'total_price' => 2500,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]));
    }

    /**
     * @param  array<int, array<int, array{dateStr: string|null, typeCounts: array<string, int>}>>  $weeks
     */
    private function cellTypeCount(array $weeks, string $date, string $type): int
    {
        foreach ($weeks as $week) {
            foreach ($week as $cell) {
                if (($cell['dateStr'] ?? null) !== $date) {
                    continue;
                }

                return (int) ($cell['typeCounts'][$type] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param  array<int, array<int, array{dateStr: string|null, isBlocked: bool}>>  $weeks
     */
    private function cellIsBlocked(array $weeks, string $date): bool
    {
        foreach ($weeks as $week) {
            foreach ($week as $cell) {
                if (($cell['dateStr'] ?? null) !== $date) {
                    continue;
                }

                return (bool) ($cell['isBlocked'] ?? false);
            }
        }

        return false;
    }
}
