<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Models\RoomChecklistItem;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Support\BookingCheckInEligibility;
use App\Support\BookingFullBalancePayment;
use App\Support\BookingLifecycleActions;
use App\Support\BookingSpecialDiscount;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RoomCalendar extends Page
{
    public const RESERVATION_ROOM = 'room';

    public const RESERVATION_VENUE = 'venue';

    public const RESERVATION_BOTH = 'both';

    protected static string $resource = BookingResource::class;

    protected static ?string $title = 'Booking Calendar';

    protected static ?string $breadcrumb = 'Booking Calendar';

    protected string $view = 'filament.resources.bookings.pages.room-calendar';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('listView')
                ->label('List view')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(BookingResource::getUrl('list')),
            Action::make('importLegacyCsv')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Import')
                ->modalDescription('Step 1: Upload your CSV file. Step 2: Keep "Check file only" turned on and click import. Step 3: If results look correct, turn it off and import again to save.')
                ->form([
                    Placeholder::make('template_path')
                        ->label('CSV Template')
                        ->content('Copy this template format: `storage/app/examples/legacy-bookings-template.csv`.'),
                    FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->disk('local')
                        ->directory('imports/legacy-bookings')
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->required(),
                    Toggle::make('dry_run')
                        ->label('Check file only (do not save yet)')
                        ->default(true),
                    Toggle::make('allow_duplicates')
                        ->label('Import even if booking may already exist')
                        ->helperText('Keep this OFF in normal use to avoid duplicate bookings.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $uploaded = $data['csv_file'] ?? null;
                    $absolutePath = null;

                    if ($uploaded instanceof TemporaryUploadedFile) {
                        $absolutePath = $uploaded->getRealPath();
                    } elseif (is_string($uploaded) && trim($uploaded) !== '') {
                        $candidate = storage_path('app/'.$uploaded);
                        $absolutePath = is_file($candidate) ? $candidate : $uploaded;
                    }

                    if (! is_string($absolutePath) || trim($absolutePath) === '' || ! is_readable($absolutePath)) {
                        Notification::make()
                            ->title('Uploaded CSV file is not readable.')
                            ->body('Please upload the file again and retry import.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $dryRun = (bool) ($data['dry_run'] ?? true);
                    $allowDuplicates = (bool) ($data['allow_duplicates'] ?? false);

                    $exitCode = Artisan::call('bookings:import-legacy-csv', [
                        'file' => $absolutePath,
                        '--dry-run' => $dryRun,
                        '--allow-duplicates' => $allowDuplicates,
                    ]);

                    $output = trim(Artisan::output());
                    $notification = Notification::make()
                        ->title($exitCode === 0 ? 'Import processed.' : 'Import failed.')
                        ->body($output !== '' ? $output : 'No command output.');

                    if ($exitCode === 0) {
                        $notification->success()->send();
                    } else {
                        $notification->danger()->send();
                    }
                }),
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

                            $livewire->redirect(BookingResource::calendarUrlForBooking($booking));
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
    public string $reservationFilter = self::RESERVATION_ROOM;

    #[Url]
    public ?string $modalDate = null;

    #[Url]
    public ?string $modalType = null;

    public function mount(): void
    {
        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) now()->month;
        }
        if ($this->year < 2000 || $this->year > 2100) {
            $this->year = (int) now()->year;
        }
        if (! in_array($this->reservationFilter, $this->reservationFilterOptions(), true)) {
            $this->reservationFilter = self::RESERVATION_ROOM;
        }

        BookingResource::markTodaysBookingsAsViewed();
    }

    public function updatedReservationFilter(): void
    {
        if (! in_array($this->reservationFilter, $this->reservationFilterOptions(), true)) {
            $this->reservationFilter = self::RESERVATION_ROOM;
        }

        $this->closeModal();
        $this->resetCalendarComputedCaches();
    }

    public function updatedMonth(): void
    {
        $this->resetCalendarComputedCaches();
    }

    public function updatedYear(): void
    {
        $this->resetCalendarComputedCaches();
    }

    protected function resetCalendarComputedCaches(): void
    {
        unset(
            $this->calendarLegendItems,
            $this->calendarWeeks,
            $this->blockedDateSetForMonth,
            $this->activeBookingRows,
            $this->modalBookingRows
        );
    }

    /**
     * @return array<string, true>
     */
    #[Computed]
    public function blockedDateSetForMonth(): array
    {
        $monthStart = Carbon::create(year: $this->year, month: $this->month, day: 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $blockedDates = collect();

        $globalBlocked = BlockedDate::query()
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());
        $blockedDates = $blockedDates->merge($globalBlocked);

        if (in_array($this->reservationFilter, [self::RESERVATION_ROOM, self::RESERVATION_BOTH], true)) {
            $roomBlocked = RoomBlockedDate::query()
                ->whereDate('blocked_on', '>=', $monthStart->toDateString())
                ->whereDate('blocked_on', '<=', $monthEnd->toDateString())
                ->pluck('blocked_on')
                ->map(fn ($date) => Carbon::parse($date)->toDateString());
            $blockedDates = $blockedDates->merge($roomBlocked);
        }

        if (in_array($this->reservationFilter, [self::RESERVATION_VENUE, self::RESERVATION_BOTH], true)) {
            $venueBlocked = VenueBlockedDate::query()
                ->whereDate('blocked_on', '>=', $monthStart->toDateString())
                ->whereDate('blocked_on', '<=', $monthEnd->toDateString())
                ->pluck('blocked_on')
                ->map(fn ($date) => Carbon::parse($date)->toDateString());
            $blockedDates = $blockedDates->merge($venueBlocked);
        }

        return $blockedDates
            ->filter(fn ($date) => is_string($date) && $date !== '')
            ->unique()
            ->mapWithKeys(fn (string $date) => [$date => true])
            ->all();
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
            if (! is_string($type) || trim($type) === '') {
                continue;
            }

            $increments[$type] = ($increments[$type] ?? 0) + max(1, (int) $line->quantity);
        }

        return $increments;
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
     * @return array<string, array<string, int>>
     */
    protected function bookingsCountByDateAndType(): array
    {
        $monthStart = Carbon::create(year: $this->year, month: $this->month, day: 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $bookings = Booking::query()
            ->whereNotIn('booking_status', [Booking::BOOKING_STATUS_CANCELLED])
            ->where('check_in', '<=', $monthEnd)
            ->where('check_out', '>=', $monthStart)
            ->with(['rooms:id,type', 'roomLines:id,booking_id,room_type,quantity', 'venues:id,name'])
            ->get();

        $map = [];

        foreach ($bookings as $booking) {
            $bookingKind = $this->bookingBadgeKindForCalendar($booking);
            if ($bookingKind !== $this->reservationFilter) {
                continue;
            }

            $incrementsPerType = $this->reservationFilter === self::RESERVATION_VENUE
                ? $this->venueIncrementsForCalendar($booking)
                : $this->roomTypeIncrementsForCalendar($booking);
            if ($incrementsPerType === []) {
                continue;
            }

            $startDay = $booking->check_in->copy()->startOfDay();
            $endDay = $booking->check_out->copy()->startOfDay();

            // Calendar display: count each calendar day from check-in through check-out (inclusive) for both rooms and venues.

            if ($endDay->lt($startDay)) {
                continue;
            }

            $from = $startDay->max($monthStart);
            $endDay = $endDay->min($monthStart->copy()->endOfMonth()->startOfDay());

            $day = $from->copy();
            while ($day->lte($endDay)) {
                if ($day->month === $this->month && $day->year === $this->year) {
                    $key = $day->toDateString();
                    $map[$key] ??= [];
                    foreach ($incrementsPerType as $type => $count) {
                        $map[$key][$type] = ($map[$key][$type] ?? 0) + $count;
                    }
                }
                $day->addDay();
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function calendarLegendItems(): array
    {
        if ($this->reservationFilter === self::RESERVATION_VENUE) {
            return Venue::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int|string $id) => [(string) $id => $name])
                ->all();
        }

        // Keep room type badges (Standard/Family/Deluxe) like previous calendar behavior.
        // For Room + Venue filter, badges still show room categories for mixed reservations.
        return Room::typeOptions();
    }

    /**
     * @return list<list<array{day: int|null, dateStr: string|null, inMonth: bool, typeCounts: array<string, int>, isBlocked: bool}>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        $counts = $this->bookingsCountByDateAndType();
        $blockedDateSet = $this->blockedDateSetForMonth;
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
                    'isBlocked' => $inMonth ? isset($blockedDateSet[$dateStr]) : false,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return list<array{id: int, reference_number: string, guest_name: string, check_in: string, check_out: string, rooms: string, venues: string, booking_status: string, payment_status: string, active_date_range: string, status_display: string, has_assigned_rooms: bool, can_pay_balance: bool, can_check_in: bool, can_mark_refund_completed: bool, booking_badge_kind: 'room'|'venue'|'both'}>
     */
    #[Computed]
    public function modalBookingRows(): array
    {
        if (! $this->modalDate || ! $this->modalType) {
            return [];
        }

        $date = Carbon::parse($this->modalDate);

        return Booking::query()
            ->overlappingCalendarInclusiveDisplay($date)
            ->where(fn ($q) => $this->applyReservationFilterQuery($q, $this->reservationFilter))
            ->where(function ($q) {
                if ($this->reservationFilter === self::RESERVATION_VENUE) {
                    $q->whereHas('venues', fn ($q2) => $q2->where('venues.id', (int) $this->modalType));

                    return;
                }

                $q->whereHas('rooms', fn ($q2) => $q2->where('type', $this->modalType))
                    ->orWhereHas('roomLines', fn ($q2) => $q2->where('room_type', $this->modalType));
            })
            ->with(['guest', 'rooms', 'roomLines', 'venues', 'roomChecklists.items'])
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $b) {
                $hasAssignedRooms = $b->rooms->isNotEmpty();
                $canCheckIn = BookingCheckInEligibility::assess($b)['allowed'];
                $activeDateRange = $this->formatActiveDateRange($b);
                $discountMeta = $this->specialDiscountMetaForBooking($b);
                $checklistSummary = $this->checkoutChecklistSummaryForBooking($b);

                return [
                    'id' => $b->id,
                    'reference_number' => $b->reference_number,
                    'guest_name' => $b->guest?->full_name ?? '—',
                    'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                    'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                    'rooms' => $b->rooms->pluck('name')->filter()->implode(', ') ?: '—',
                    'venues' => $b->venues->pluck('name')->filter()->implode(', ') ?: '—',
                    'booking_status' => (string) $b->booking_status,
                    'payment_status' => (string) $b->payment_status,
                    'active_date_range' => $activeDateRange,
                    'status_display' => (Booking::bookingStatusOptions()[(string) $b->booking_status] ?? (string) $b->booking_status)
                        .' · '
                        .(Booking::paymentStatusOptions()[(string) $b->payment_status] ?? (string) $b->payment_status),
                    'has_assigned_rooms' => $hasAssignedRooms,
                    'can_pay_balance' => $this->canPayBalanceForBooking($b),
                    'can_check_in' => $canCheckIn,
                    'can_complete' => $b->canAdminCheckout(),
                    'complete_label' => $b->adminCheckoutActionLabel(),
                    'can_mark_refund_completed' => $this->canMarkRefundCompletedForBooking($b),
                    'booking_badge_kind' => $this->bookingBadgeKindForCalendar($b),
                    'has_special_discount' => $discountMeta['has_special_discount'],
                    'discount_badge_text' => $discountMeta['discount_badge_text'],
                    'discount_tooltip' => $discountMeta['discount_tooltip'],
                    'checklist_summary' => $checklistSummary,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, reference_number: string, guest_name: string, check_in: string, check_out: string, rooms: string, venues: string, booking_status: string, payment_status: string, active_date_range: string, status_display: string, has_assigned_rooms: bool, can_pay_balance: bool, can_check_in: bool, can_mark_refund_completed: bool, booking_badge_kind: 'room'|'venue'|'both'}>
     */
    #[Computed]
    public function activeBookingRows(): array
    {
        $today = now()->startOfDay();

        return Booking::query()
            ->overlappingCalendarInclusiveDisplay($today)
            ->whereNotIn('booking_status', [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED])
            ->with(['guest', 'rooms', 'roomLines', 'venues'])
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $b) {
                $hasAssignedRooms = $b->rooms->isNotEmpty();
                $canCheckIn = BookingCheckInEligibility::assess($b)['allowed'];
                $activeDateRange = $this->formatActiveDateRange($b);
                $discountMeta = $this->specialDiscountMetaForBooking($b);

                return [
                    'id' => $b->id,
                    'reference_number' => $b->reference_number,
                    'guest_name' => $b->guest?->full_name ?? '—',
                    'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                    'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                    'rooms' => $b->rooms->pluck('name')->filter()->implode(', ') ?: '—',
                    'venues' => $b->venues->pluck('name')->filter()->implode(', ') ?: '—',
                    'booking_status' => (string) $b->booking_status,
                    'payment_status' => (string) $b->payment_status,
                    'active_date_range' => $activeDateRange,
                    'status_display' => (Booking::bookingStatusOptions()[(string) $b->booking_status] ?? (string) $b->booking_status)
                        .' · '
                        .(Booking::paymentStatusOptions()[(string) $b->payment_status] ?? (string) $b->payment_status),
                    'has_assigned_rooms' => $hasAssignedRooms,
                    'can_pay_balance' => $this->canPayBalanceForBooking($b),
                    'can_check_in' => $canCheckIn,
                    'can_complete' => $b->canAdminCheckout(),
                    'complete_label' => $b->adminCheckoutActionLabel(),
                    'can_mark_refund_completed' => $this->canMarkRefundCompletedForBooking($b),
                    'booking_badge_kind' => $this->bookingBadgeKindForCalendar($b),
                    'has_special_discount' => $discountMeta['has_special_discount'],
                    'discount_badge_text' => $discountMeta['discount_badge_text'],
                    'discount_tooltip' => $discountMeta['discount_tooltip'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{has_special_discount: bool, discount_badge_text: string, discount_tooltip: string}
     */
    protected function specialDiscountMetaForBooking(Booking $booking): array
    {
        if (! BookingSpecialDiscount::hasDiscount($booking)) {
            return [
                'has_special_discount' => false,
                'discount_badge_text' => '',
                'discount_tooltip' => '',
            ];
        }

        $amount = BookingSpecialDiscount::discountAmount($booking);
        $gross = BookingSpecialDiscount::grossTotal($booking);
        $type = (string) ($booking->special_discount_type ?? '');
        $target = BookingSpecialDiscount::resolveDiscountTarget($booking, (string) ($booking->special_discount_target ?? null));
        $value = (float) ($booking->special_discount_value ?? 0);

        $valueLabel = $type === BookingSpecialDiscount::TYPE_PERCENT
            ? rtrim(rtrim(number_format($value, 2), '0'), '.').'%'
            : 'PHP '.number_format($value, 2);

        $targetLabel = match ($target) {
            BookingSpecialDiscount::TARGET_ROOM => 'room subtotal',
            BookingSpecialDiscount::TARGET_VENUE => 'venue subtotal',
            default => 'grand total',
        };

        return [
            'has_special_discount' => true,
            'discount_badge_text' => 'Discounted',
            'discount_tooltip' => sprintf(
                'Special discount applied on %s: %s off (PHP %s).',
                $targetLabel,
                $valueLabel,
                number_format($amount, 2),
            ),
        ];
    }

    protected function canPayBalanceForBooking(Booking $booking): bool
    {
        return BookingFullBalancePayment::assess($booking)['allowed'];
    }

    protected function canMarkRefundCompletedForBooking(Booking $booking): bool
    {
        return in_array($booking->booking_status, [
            Booking::BOOKING_STATUS_RESCHEDULED,
            Booking::BOOKING_STATUS_CANCELLED,
        ], true)
            && $booking->payment_status === Booking::PAYMENT_STATUS_REFUND_PENDING;
    }

    protected function formatActiveDateRange(Booking $booking): string
    {
        $checkIn = $booking->check_in;
        $checkOut = $booking->check_out;

        if (! $checkIn || ! $checkOut) {
            return '—';
        }

        return $checkIn->isSameDay($checkOut)
            ? $checkIn->format('F j')
            : $checkIn->format('F j').' - '.$checkOut->format('F j');
    }

    /**
     * @return array{
     *   total_items: int,
     *   answered_items: int,
     *   incomplete_items: int,
     *   broken_items: int,
     *   missing_items: int,
     *   has_damage_items: bool,
     *   should_warn_on_complete: bool
     * }
     */
    protected function checkoutChecklistSummaryForBooking(Booking $booking): array
    {
        $items = $booking->roomChecklists
            ->flatMap(fn ($checklist) => $checklist->items)
            ->values();

        $totalItems = (int) $items->count();
        $answeredItems = (int) $items->filter(fn ($item) => filled($item->status))->count();
        $brokenItems = (int) $items->where('status', RoomChecklistItem::STATUS_BROKEN)->count();
        $missingItems = (int) $items->where('status', RoomChecklistItem::STATUS_MISSING)->count();
        $incompleteItems = max(0, $totalItems - $answeredItems);

        return [
            'total_items' => $totalItems,
            'answered_items' => $answeredItems,
            'incomplete_items' => $incompleteItems,
            'broken_items' => $brokenItems,
            'missing_items' => $missingItems,
            'has_damage_items' => ($brokenItems + $missingItems) > 0,
            'should_warn_on_complete' => $incompleteItems > 0,
        ];
    }

    /**
     * Room-only, venue-only, or combined badge for calendar modal rows.
     *
     * @return 'room'|'venue'|'both'
     */
    protected function bookingBadgeKindForCalendar(Booking $booking): string
    {
        $hasRooms = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        $hasVenues = $booking->venues->isNotEmpty();

        if ($hasRooms && $hasVenues) {
            return 'both';
        }

        if ($hasVenues) {
            return 'venue';
        }

        return 'room';
    }

    protected function applyReservationFilterQuery($query, string $filter): void
    {
        if ($filter === self::RESERVATION_BOTH) {
            $query
                ->where(function ($q) {
                    $q->whereHas('rooms')
                        ->orWhereHas('roomLines');
                })
                ->whereHas('venues');

            return;
        }

        if ($filter === self::RESERVATION_VENUE) {
            $query
                ->whereHas('venues')
                ->whereDoesntHave('rooms')
                ->whereDoesntHave('roomLines');

            return;
        }

        $query
            ->where(function ($q) {
                $q->whereHas('rooms')
                    ->orWhereHas('roomLines');
            })
            ->whereDoesntHave('venues');
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

        if ($this->reservationFilter === self::RESERVATION_VENUE) {
            return Venue::query()->whereKey((int) $this->modalType)->value('name') ?? 'Venue';
        }

        return Room::typeOptions()[$this->modalType] ?? ucfirst($this->modalType);
    }

    /**
     * @return list<string>
     */
    public function reservationFilterOptions(): array
    {
        return [
            self::RESERVATION_ROOM,
            self::RESERVATION_VENUE,
            self::RESERVATION_BOTH,
        ];
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

        try {
            BookingFullBalancePayment::record($booking);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Cannot record payment')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Balance paid successfully.')
            ->success()
            ->send();
    }

    public function checkInBooking(int $bookingId): void
    {
        $booking = Booking::query()
            ->with(['roomLines', 'venues', 'rooms.bedSpecifications'])
            ->find($bookingId);

        if (! $booking) {
            return;
        }

        try {
            BookingLifecycleActions::checkIn($booking);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Cannot check in')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Booking checked in.')
            ->success()
            ->send();
    }

    public function completeBooking(
        int $bookingId,
        bool $confirmed = false,
        bool $includeDamageChecklist = false,
        array $damageChecklist = [],
    ): void
    {
        $booking = Booking::query()
            ->with(['roomChecklists.items'])
            ->find($bookingId);

        if (! $booking) {
            return;
        }

        $checklistSummary = $this->checkoutChecklistSummaryForBooking($booking);
        if (! $confirmed && ($checklistSummary['should_warn_on_complete'] ?? false)) {
            Notification::make()
                ->title('Checklist is not fully complete.')
                ->body('Some checklist items are still incomplete. Review checklist details and confirm "Complete anyway" to proceed.')
                ->warning()
                ->send();

            return;
        }

        try {
            if ($includeDamageChecklist) {
                BookingLifecycleActions::saveCheckoutChecklistItems($booking, $damageChecklist);
            }
            BookingLifecycleActions::complete($booking);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Cannot complete')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Booking marked as completed.')
            ->success()
            ->send();
    }

    public function cancelBooking(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking) {
            return;
        }

        try {
            BookingLifecycleActions::cancel($booking);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Cannot cancel')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Booking cancelled.')
            ->success()
            ->send();
    }

    public function markRefundCompleted(int $bookingId): void
    {
        $booking = Booking::query()->find($bookingId);

        if (! $booking || ! $this->canMarkRefundCompletedForBooking($booking)) {
            return;
        }

        $booking->update([
            'payment_status' => Booking::PAYMENT_STATUS_REFUNDED,
        ]);

        Notification::make()
            ->title('Refund marked as completed.')
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
            ->title('Booking moved to Recycle Bin.')
            ->success()
            ->send();
    }
}
