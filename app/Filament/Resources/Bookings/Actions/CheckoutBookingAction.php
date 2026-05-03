<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Models\Booking;
use App\Models\InspectionItem;
use App\Support\BookingInspectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;

final class CheckoutBookingAction
{
    /**
     * Header / edit page actions (record resolved via callback).
     *
     * @param  callable(): mixed  $getBooking
     * @param  callable(Booking): void  $afterSuccess
     */
    public static function makeForRecordCallbacks(string $name, callable $getBooking, callable $afterSuccess): Action
    {
        return self::applyInventoryModalChrome(
            Action::make($name)
                ->label(fn (): string => self::resolveBooking($getBooking)?->adminCheckoutActionLabel() ?? __('Checkout'))
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->modalWidth(Width::SevenExtraLarge)
                ->visible(function () use ($getBooking): bool {
                    $record = self::resolveBooking($getBooking);

                    return $record instanceof Booking
                        && $record->canAdminCheckout()
                        && ! $record->bookingInspection;
                })
                ->fillForm(fn (): array => self::buildFillForm(self::resolveBooking($getBooking)))
                ->form(fn (): array => self::buildSchema(self::resolveBooking($getBooking)))
                ->action(function (array $data) use ($getBooking, $afterSuccess): void {
                    $record = self::resolveBooking($getBooking);
                    if (! $record instanceof Booking) {
                        return;
                    }

                    self::runCheckout($record, $data, $afterSuccess);
                }),
            fn (?Booking $ignored = null): ?Booking => self::resolveBooking($getBooking),
        );
    }

    /**
     * Table row actions: Filament passes the booking model into schema callbacks.
     */
    public static function makeTableAction(string $name = 'complete'): Action
    {
        return self::applyInventoryModalChrome(
            Action::make($name)
                ->label(fn (?Booking $record): string => $record instanceof Booking
                    ? $record->adminCheckoutActionLabel()
                    : __('Checkout'))
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->modalWidth(Width::SevenExtraLarge)
                ->visible(function (?Booking $record): bool {
                    return $record instanceof Booking
                        && $record->canAdminCheckout()
                        && ! $record->bookingInspection;
                })
                ->fillForm(fn (?Booking $record): array => self::buildFillForm($record))
                ->form(fn (?Booking $record): array => self::buildSchema($record))
                ->action(function (...$arguments): void {
                    $record = null;
                    $data = [];
                    foreach ($arguments as $argument) {
                        if ($argument instanceof Booking) {
                            $record = $argument;
                        }
                        if (is_array($argument)) {
                            $data = $argument;
                        }
                    }

                    if (! $record instanceof Booking) {
                        return;
                    }

                    self::runCheckout($record, $data, static function (): void {
                        //
                    });
                }),
            fn (?Booking $record = null): ?Booking => $record,
        );
    }

    /**
     * @param  callable(?Booking): ?Booking  $resolveBooking
     */
    private static function applyInventoryModalChrome(Action $action, callable $resolveBooking): Action
    {
        return $action
            ->modalHeading(function (?Booking $record = null) use ($resolveBooking): string {
                $r = $resolveBooking($record);

                return $r instanceof Booking && BookingInspectionService::bookingNeedsInventoryInspection($r)
                    ? __('Checkout')
                    : ($r instanceof Booking ? $r->adminCheckoutActionLabel() : __('Checkout'));
            })
            ->modalDescription(function (?Booking $record = null) use ($resolveBooking): ?string {
                $r = $resolveBooking($record);

                return $r instanceof Booking && BookingInspectionService::bookingNeedsInventoryInspection($r)
                    ? __('Mark each item as OK, Damaged, or Missing before completing checkout.')
                    : __('Confirm to mark this stay as completed.');
            })
            ->modalSubmitActionLabel(function (?Booking $record = null) use ($resolveBooking): string {
                $r = $resolveBooking($record);

                return $r instanceof Booking && BookingInspectionService::bookingNeedsInventoryInspection($r)
                    ? __('Complete checkout')
                    : __('Confirm checkout');
            })
            ->modalCancelActionLabel(__('Go back'))
            ->modalSubmitAction(fn (Action $submit): Action => $submit->color('success'));
    }

    /**
     * @param  callable(Booking): void  $afterSuccess
     */
    private static function runCheckout(Booking $record, array $data, callable $afterSuccess): void
    {
        try {
            if (BookingInspectionService::bookingNeedsInventoryInspection($record)) {
                BookingInspectionService::submitAndCheckout($record, $data);
                $record->refresh();
                $title = $record->booking_status === Booking::BOOKING_STATUS_FLAGGED
                    ? __('Checkout recorded — booking flagged for follow-up.')
                    : __('Checkout complete.');
                Notification::make()
                    ->title($title)
                    ->success()
                    ->send();
            } else {
                BookingInspectionService::simpleCheckout($record);
                Notification::make()
                    ->title(__('Booking marked as completed.'))
                    ->success()
                    ->send();
            }
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot complete checkout'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        $afterSuccess($record);
    }

    /**
     * @param  callable(): mixed  $getBooking
     */
    private static function resolveBooking(callable $getBooking): ?Booking
    {
        $record = $getBooking();

        return $record instanceof Booking ? $record : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildFillForm(?Booking $record): array
    {
        if (! $record instanceof Booking || ! BookingInspectionService::bookingNeedsInventoryInspection($record)) {
            return [];
        }

        return [
            'bulk_status' => null,
            'items' => BookingInspectionService::defaultFormItems($record),
            'notes' => null,
        ];
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    private static function buildSchema(?Booking $record): array
    {
        if (! $record instanceof Booking || ! BookingInspectionService::bookingNeedsInventoryInspection($record)) {
            return [
                Placeholder::make('quick_info')
                    ->label('')
                    ->content(__('No room inventory list is configured for the assigned room(s). Confirm to mark this stay as completed.')),
            ];
        }

        return [
            Select::make('bulk_status')
                ->label(__('Quick actions'))
                ->placeholder('—')
                ->options([
                    'ok' => __('Mark all as OK'),
                ])
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    if ($state !== 'ok') {
                        return;
                    }
                    $items = $get('items') ?? [];
                    foreach (array_keys($items) as $index) {
                        $set("items.{$index}.status", InspectionItem::STATUS_OK);
                    }
                    $set('bulk_status', null);
                }),

            ...self::groupedInventorySections($record),

            Textarea::make('notes')
                ->label(__('Inspection notes'))
                ->rows(2)
                ->placeholder(__('Optional notes for this inspection.'))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function groupedInventorySections(Booking $record): array
    {
        $flat = BookingInspectionService::defaultFormItems($record);
        $grouped = [];
        foreach ($flat as $idx => $row) {
            $room = (string) ($row['room_name'] ?? __('Room'));
            $grouped[$room][] = (int) $idx;
        }

        $sections = [];
        foreach ($grouped as $roomName => $indices) {
            $rows = [];
            foreach ($indices as $i) {
                $base = "items.{$i}";
                $rows[] = Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        Hidden::make("{$base}.inventory_item_id"),
                        Grid::make(['default' => 1, 'lg' => 12])
                            ->columnSpanFull()
                            ->schema([
                                Placeholder::make("{$base}_label")
                                    ->columnSpan(['default' => 1, 'lg' => 5])
                                    ->label('')
                                    ->content(function (Get $get) use ($base): HtmlString {
                                        $name = (string) ($get("{$base}.item_name") ?? '');
                                        $qty = (int) ($get("{$base}.quantity") ?? 1);

                                        return new HtmlString(
                                            '<div class="flex min-h-[2.75rem] flex-col justify-center gap-0.5 py-1>'
                                            .'<span class="text-base font-semibold leading-tight text-white>'
                                            .e($name)
                                            .'</span>'
                                            .'<span class="text-xs text-gray-300>'
                                            .e(__('Qty')).' '.$qty
                                            .'</span>'
                                            .'</div>'
                                        );
                                    }),
                                ToggleButtons::make("{$base}.status")
                                    ->label('')
                                    ->columnSpan(['default' => 1, 'lg' => 7])
                                    ->options([
                                        InspectionItem::STATUS_OK => __('OK'),
                                        InspectionItem::STATUS_DAMAGED => __('Damaged'),
                                        InspectionItem::STATUS_MISSING => __('Missing'),
                                    ])
                                    ->colors([
                                        InspectionItem::STATUS_OK => 'success',
                                        InspectionItem::STATUS_DAMAGED => 'warning',
                                        InspectionItem::STATUS_MISSING => 'danger',
                                    ])
                                    ->icons([
                                        InspectionItem::STATUS_OK => 'heroicon-o-check-circle',
                                        InspectionItem::STATUS_DAMAGED => 'heroicon-o-wrench-screwdriver',
                                        InspectionItem::STATUS_MISSING => 'heroicon-o-exclamation-triangle',
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->required()
                                    ->live(),
                            ]),
                        Textarea::make("{$base}.remarks")
                            ->label(__('Remarks'))
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => in_array((string) $get("{$base}.status"), [
                                InspectionItem::STATUS_DAMAGED,
                                InspectionItem::STATUS_MISSING,
                            ], true)),
                        FileUpload::make("{$base}.photos")
                            ->label(__('Photo evidence'))
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->directory('inspections')
                            ->visibility('public')
                            ->nullable()
                            ->columnSpanFull()
                            ->helperText(__('Required when status is Damaged or Missing.'))
                            ->visible(fn (Get $get) => in_array((string) $get("{$base}.status"), [
                                InspectionItem::STATUS_DAMAGED,
                                InspectionItem::STATUS_MISSING,
                            ], true)),
                    ])
                    ->extraAttributes([
                        'class' => 'rounded-xl border border-[#1f2a44] bg-[#0e2036] px-4 py-3 shadow-sm',
                    ]);
            }

            $sections[] = Section::make($roomName)
                ->schema($rows)
                ->columnSpanFull()
                ->compact()
                ->extraAttributes([
                    'class' => 'fi-checkout-inventory-room',
                ]);
        }

        return $sections;
    }
}
