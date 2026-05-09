<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Support\SettledDamageLossRevenue;
use Tests\TestCase;

class SettledDamageLossRevenueTest extends TestCase
{
    public function test_it_calculates_settled_damage_and_loss_revenue_from_loaded_checklists(): void
    {
        $booking = new Booking([
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED,
            'total_price' => 1000,
        ]);

        $checklist = new RoomChecklist();
        $checklist->setRelation('room', null);

        $item = new RoomChecklistItem([
            'label' => 'Broken lamp',
            'charge' => '150.00',
            'quantity' => 2,
            'status' => RoomChecklistItem::STATUS_BROKEN,
        ]);

        $item->setRelation('roomChecklist', $checklist);
        $checklist->setRelation('items', collect([$item]));
        $booking->setRelation('roomChecklists', collect([$checklist]));

        $this->assertSame(300.0, SettledDamageLossRevenue::forBooking($booking));
    }

    public function test_it_ignores_bookings_that_are_not_settled(): void
    {
        $booking = new Booking([
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
            'total_price' => 1000,
        ]);

        $checklist = new RoomChecklist();
        $checklist->setRelation('room', null);

        $item = new RoomChecklistItem([
            'label' => 'Broken lamp',
            'charge' => '150.00',
            'quantity' => 2,
            'status' => RoomChecklistItem::STATUS_BROKEN,
        ]);

        $item->setRelation('roomChecklist', $checklist);
        $checklist->setRelation('items', collect([$item]));
        $booking->setRelation('roomChecklists', collect([$checklist]));

        $this->assertSame(0.0, SettledDamageLossRevenue::forBooking($booking));
    }
}