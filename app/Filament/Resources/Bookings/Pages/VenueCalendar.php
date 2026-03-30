<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Venue;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;

class VenueCalendar extends Page
{
    protected static string $resource = BookingResource::class;

    protected static ?string $title = 'Venue Calendar';

    protected static ?string $breadcrumb = 'Venue Calendar';

    protected string $view = 'filament.resources.bookings.pages.venue-calendar';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('roomCalendar')
                ->label('Room calendar')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(BookingResource::getUrl('roomCalendar')),
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

                            $decoded = json_decode($payload, true);
                            $reference = is_array($decoded) ? ($decoded['reference'] ?? null) : null;
                            $reference = $reference ?: trim($payload);

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

                            $livewire->redirect(BookingResource::getUrl('view', ['record' => $booking]));
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

    public ?string $modalDate = null;

    public ?int $modalVenueId = null;

    #[Computed]
    public function venues()
    {
        return Venue::query()->where('status', '!=', Venue::STATUS_MAINTENANCE)->get();
    }

    public function mount(): void
    {
        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) now()->month;
        }
        if ($this->year < 2000 || $this->year > 2100) {
            $this->year = (int) now()->year;
        }
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

    public function openDayType(string $date, int $venueId): void
    {
        $this->modalDate = $date;
        $this->modalVenueId = $venueId;
    }

    public function closeModal(): void
    {
        $this->modalDate = null;
        $this->modalVenueId = null;
    }

    /**
     * @return array<string, array<int, int>>
     */
    protected function bookingsCountByDateAndVenue(): array
    {
        $monthStart = Carbon::create(year: $this->year, month: $this->month, day: 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $bookings = Booking::query()
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->where('check_in', '<=', $monthEnd)
            ->where('check_out', '>', $monthStart)
            ->with(['venues:id,name'])
            ->get();

        $map = [];

        foreach ($bookings as $booking) {
            if ($booking->venues->isEmpty()) {
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
                    foreach ($booking->venues as $venue) {
                        $map[$key] ??= [];
                        $map[$key][$venue->id] = ($map[$key][$venue->id] ?? 0) + 1;
                    }
                }
                $day->addDay();
            }
        }

        return $map;
    }

    /**
     * @return list<list<array{day: int|null, dateStr: string|null, inMonth: bool, venueCounts: array<int, int>}>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        $counts = $this->bookingsCountByDateAndVenue();
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
                    'venueCounts' => $inMonth ? ($counts[$dateStr] ?? []) : [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return list<array{id: int, reference_number: string, guest_name: string, check_in: string, check_out: string, venues: string, status: string}>
     */
    #[Computed]
    public function modalBookingRows(): array
    {
        if (! $this->modalDate || ! $this->modalVenueId) {
            return [];
        }

        $date = Carbon::parse($this->modalDate);

        return Booking::query()
            ->overlappingLodgingNight($date)
            ->whereHas('venues', fn ($q) => $q->where('venues.id', $this->modalVenueId))
            ->with(['guest', 'venues'])
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $b) {
                return [
                    'id' => $b->id,
                    'reference_number' => $b->reference_number,
                    'guest_name' => $b->guest?->full_name ?? '—',
                    'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                    'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                    'venues' => $b->venues->pluck('name')->filter()->implode(', ') ?: '—',
                    'status' => $b->status,
                ];
            })
            ->values()
            ->all();
    }

    public function modalHeadingLabel(): string
    {
        if (! $this->modalDate) {
            return '';
        }

        return Carbon::parse($this->modalDate)->format('l, F j, Y');
    }

    public function modalVenueLabel(): string
    {
        if (! $this->modalVenueId) {
            return '';
        }

        return Venue::query()->find($this->modalVenueId)?->name ?? 'Venue';
    }

    public function modalVenue(): ?Venue
    {
        if (! $this->modalVenueId) {
            return null;
        }
        return Venue::query()->find($this->modalVenueId);
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

        if ($booking->balance <= 0 || $booking->status === Booking::STATUS_CANCELLED) {
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

    public function deleteBooking(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking) {
            return;
        }

        $booking->delete();

        Notification::make()
            ->title('Booking deleted.')
            ->success()
            ->send();
    }
}
