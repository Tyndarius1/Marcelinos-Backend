<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Venue;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class RoomCalendar extends Page
{
    public const INVENTORY_ROOMS = 'rooms';

    public const INVENTORY_VENUES = 'venues';

    protected static string $resource = BookingResource::class;

    protected static ?string $title = 'Booking Calendar';

    protected static ?string $breadcrumb = 'Booking Calendar';

    protected string $view = 'filament.resources.bookings.pages.room-calendar';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('venueCalendar')
                ->label('Venue calendar')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->url(BookingResource::getUrl('venueCalendar')),
            Action::make('listView')
                ->label('List view')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(BookingResource::getUrl('list')),
            CreateAction::make(),
            Action::make('scanQr')
                ->label('Scan QR')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->modalHeading('Scan Booking QR Code')
                ->modalDescription('Open your camera and hold the guest\'s booking QR code within the frame to look up their reservation instantly.')
                ->modalWidth('md')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->form([
                    QrCodeInput::make('qr_payload')
                        ->hiddenLabel()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, $livewire): void {
                            $payload = $state;

                            if (! $payload) {
                                Notification::make()
                                    ->title('No QR code data found.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $cleanPayload = trim($payload);
                            $cleanPayload = preg_replace('/^\xEF\xBB\xBF/', '', $cleanPayload) ?? $cleanPayload;

                            $decoded = json_decode($cleanPayload, true);
                            if (! is_array($decoded) && is_string($decoded)) {
                                $inner = json_decode($decoded, true);
                                if (is_array($inner)) {
                                    $decoded = $inner;
                                }
                            }

                            $reference = is_array($decoded)
                                ? ($decoded['reference_number'] ?? $decoded['reference'] ?? null)
                                : null;

                            if (! is_string($reference) || trim($reference) === '') {
                                // Fallback: extract booking reference from arbitrary text/URL.
                                if (preg_match('/\bMWA-\d{4}-\d{6}\b/', $cleanPayload, $matches) === 1) {
                                    $reference = $matches[0];
                                } else {
                                    $reference = trim($cleanPayload);
                                }
                            }

                            $booking = Booking::query()
                                ->where('reference_number', $reference)
                                ->first();

                            if (! $booking) {
                                Notification::make()
                                    ->title('Booking not found.')
                                    ->body('The scanned QR code did not match any booking. Please try again.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $livewire->redirect(BookingResource::getUrl('edit', ['record' => $booking]));
                        }),
                ])
                ->action(fn () => null),
        ];
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    #[Url]
    public int $month = 0;

    #[Url]
    public int $year = 0;

    #[Url]
    public string $inventory = self::INVENTORY_ROOMS;

    public ?string $modalDate = null;

    public ?string $modalType = null;

    public function mount(): void
    {
        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) now()->month;
        }
        if ($this->year < 2000 || $this->year > 2100) {
            $this->year = (int) now()->year;
        }
        if (! in_array($this->inventory, [self::INVENTORY_ROOMS, self::INVENTORY_VENUES], true)) {
            $this->inventory = self::INVENTORY_ROOMS;
        }
    }

    public function switchInventory(string $inventory): void
    {
        if (! in_array($inventory, [self::INVENTORY_ROOMS, self::INVENTORY_VENUES], true)) {
            return;
        }

        $this->inventory = $inventory;
        $this->closeModal();
    }

    public function previousMonth(): void
    {
        $d = Carbon::create(year: $this->year, month: $this->month, day: 1)->subMonth();
        $this->month = (int) $d->month;
        $this->year = (int) $d->year;
    }

    public function nextMonth(): void
    {
        $d = Carbon::create(year: $this->year, month: $this->month, day: 1)->addMonth();
        $this->month = (int) $d->month;
        $this->year = (int) $d->year;
    }

    public function openDayType(string $date, string $type): void
    {
        $this->modalDate = $date;
        $this->modalType = $type;
    }

    public function closeModal(): void
    {
        $this->modalDate = null;
        $this->modalType = null;
    }

    /**
     * Counts per room category for one booking. Prefers assigned physical rooms; otherwise uses
     * guest room lines (frontend bookings before staff assigns specific rooms).
     *
     * @return array<string, int>
     */
    protected function roomTypeIncrementsForCalendar(Booking $booking): array
    {
        if ($booking->rooms->isNotEmpty()) {
            $increments = [];
            foreach ($booking->rooms->pluck('type')->unique()->filter() as $type) {
                $increments[$type] = ($increments[$type] ?? 0) + 1;
            }

            return $increments;
        }

        $increments = [];
        foreach ($booking->roomLines as $line) {
            $type = $line->room_type;
            $increments[$type] = ($increments[$type] ?? 0) + max(1, (int) $line->quantity);
        }

        return $increments;
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected function bookingsCountByDateAndType(): array
    {
        $monthStart = Carbon::create(year: $this->year, month: $this->month, day: 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $bookings = Booking::query()
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->where('check_in', '<=', $monthEnd)
            ->where('check_out', '>', $monthStart)
            ->with(['rooms:id,type', 'roomLines', 'venues:id,name'])
            ->get();

        $map = [];

        foreach ($bookings as $booking) {
            $incrementsPerType = $this->inventory === self::INVENTORY_VENUES
                ? $this->venueIncrementsForCalendar($booking)
                : $this->roomTypeIncrementsForCalendar($booking);
            if ($incrementsPerType === []) {
                continue;
            }

            $firstNight = $booking->check_in->copy()->startOfDay();
            $lastNight = $booking->check_out->copy()->startOfDay()->subDay();
            if ($lastNight->lt($firstNight)) {
                continue;
            }

            $from = $firstNight->max($monthStart);
            $endDay = $lastNight->min($monthStart->copy()->endOfMonth()->startOfDay());

            $day = $from->copy();
            while ($day->lte($endDay)) {
                if ($day->month === $this->month && $day->year === $this->year) {
                    $key = $day->toDateString();
                    foreach ($incrementsPerType as $type => $n) {
                        $map[$key] ??= [];
                        $map[$key][$type] = ($map[$key][$type] ?? 0) + $n;
                    }
                }
                $day->addDay();
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    protected function venueIncrementsForCalendar(Booking $booking): array
    {
        $increments = [];

        foreach ($booking->venues as $venue) {
            $key = (string) $venue->id;
            $increments[$key] = ($increments[$key] ?? 0) + 1;
        }

        return $increments;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function calendarLegendItems(): array
    {
        if ($this->inventory === self::INVENTORY_VENUES) {
            return Venue::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id) => [(string) $id => $name])
                ->all();
        }

        return Room::typeOptions();
    }

    /**
     * @return list<list<array{day: int|null, dateStr: string|null, inMonth: bool, typeCounts: array<string, int>}>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        $counts = $this->bookingsCountByDateAndType();
        $monthStart = Carbon::create(year: $this->year, month: $this->month, day: 1);
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $inMonth = $cursor->month === $this->month && $cursor->year === $this->year;
                $dateStr = $cursor->toDateString();
                $week[] = [
                    'day' => $inMonth ? $cursor->day : null,
                    'dateStr' => $inMonth ? $dateStr : null,
                    'inMonth' => $inMonth,
                    'typeCounts' => $inMonth ? ($counts[$dateStr] ?? []) : [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return list<array{id: int, reference_number: string, guest_name: string, check_in: string, check_out: string, rooms: string, venues: string, status: string, has_assigned_rooms: bool, can_pay_balance: bool}>
     */
    #[Computed]
    public function modalBookingRows(): array
    {
        if (! $this->modalDate || ! $this->modalType) {
            return [];
        }

        $date = Carbon::parse($this->modalDate);

        return Booking::query()
            ->overlappingLodgingNight($date)
            ->where(function ($q) {
                $type = $this->modalType;
                if ($this->inventory === self::INVENTORY_VENUES) {
                    $q->whereHas('venues', fn ($q2) => $q2->where('venues.id', (int) $type));

                    return;
                }

                $q->whereHas('rooms', fn ($q2) => $q2->where('type', $type))
                    ->orWhereHas('roomLines', fn ($q2) => $q2->where('room_type', $type));
            })
            ->with(['guest', 'rooms', 'roomLines', 'venues'])
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $b) {
                $hasAssignedRooms = $b->rooms->isNotEmpty();

                return [
                    'id' => $b->id,
                    'reference_number' => $b->reference_number,
                    'guest_name' => $b->guest?->full_name ?? '—',
                    'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                    'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                    'rooms' => $b->rooms->pluck('name')->filter()->implode(', ') ?: '—',
                    'venues' => $b->venues->pluck('name')->filter()->implode(', ') ?: '—',
                    'status' => $b->status,
                    'has_assigned_rooms' => $hasAssignedRooms,
                    'can_pay_balance' => $this->canPayBalanceForBooking($b, $hasAssignedRooms),
                ];
            })
            ->values()
            ->all();
    }

    protected function canPayBalanceForBooking(Booking $booking, ?bool $hasAssignedRooms = null): bool
    {
        $hasAssignedRooms = $hasAssignedRooms ?? $booking->rooms()->exists();

        if (! $hasAssignedRooms) {
            return false;
        }

        if (in_array($booking->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_PAID,
        ], true)) {
            return false;
        }

        return (float) $booking->balance > 0;
    }

    public function modalHeadingLabel(): string
    {
        if (! $this->modalDate) {
            return '';
        }

        return Carbon::parse($this->modalDate)->format('l, F j, Y');
    }

    public function modalTypeLabel(): string
    {
        if (! $this->modalType) {
            return '';
        }

        if ($this->inventory === self::INVENTORY_VENUES) {
            return Venue::query()->whereKey((int) $this->modalType)->value('name') ?? 'Venue';
        }

        return Room::typeOptions()[$this->modalType] ?? ucfirst($this->modalType);
    }

    public function isVenueMode(): bool
    {
        return $this->inventory === self::INVENTORY_VENUES;
    }

    /**
     * @return list<int>
     */
    public function yearOptions(): array
    {
        $y = (int) now()->year;

        return range($y - 2, $y + 3);
    }

    public function currentPeriodLabel(): string
    {
        return Carbon::create(year: $this->year, month: $this->month, day: 1)->format('F Y');
    }

    public function payBalance(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking) {
            return;
        }

        if (! $this->canPayBalanceForBooking($booking)) {
            return;
        }

        $booking->payments()->create([
            'total_amount' => $booking->total_price,
            'partial_amount' => $booking->balance,
            'is_fullypaid' => true,
        ]);
        $booking->update(['status' => Booking::STATUS_PAID]);

        Notification::make()
            ->title('Balance paid successfully.')
            ->success()
            ->send();
    }

    public function checkInBooking(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking || $booking->status !== Booking::STATUS_PAID) {
            return;
        }

        $booking->update(['status' => Booking::STATUS_OCCUPIED]);

        Notification::make()
            ->title('Booking checked in.')
            ->success()
            ->send();
    }

    public function completeBooking(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking || $booking->status !== Booking::STATUS_OCCUPIED) {
            return;
        }

        $booking->update(['status' => Booking::STATUS_COMPLETED]);

        Notification::make()
            ->title('Booking marked as completed.')
            ->success()
            ->send();
    }

    public function cancelBooking(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking || in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
            return;
        }

        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        Notification::make()
            ->title('Booking cancelled.')
            ->success()
            ->send();
    }

    public function deleteBooking(int $bookingId, string $confirmation): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking) {
            return;
        }

        if (trim($confirmation) !== $booking->reference_number) {
            Notification::make()
                ->title('Confirmation does not match the booking reference.')
                ->danger()
                ->send();

            return;
        }

        $booking->delete();

        Notification::make()
            ->title('Booking deleted.')
            ->success()
            ->send();
    }
}
