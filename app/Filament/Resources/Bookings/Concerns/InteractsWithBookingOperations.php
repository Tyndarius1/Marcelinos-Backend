<?php

namespace App\Filament\Resources\Bookings\Concerns;

use App\Filament\Resources\Bookings\Actions\CheckoutBookingAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Support\BookingAdminGuidance;
use App\Support\BookingCheckInEligibility;
use App\Support\BookingDamageSettlement;
use App\Support\BookingFullBalancePayment;
use App\Support\BookingLifecycleActions;
use App\Support\BookingSpecialDiscount;
use App\Support\CancellationPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;

trait InteractsWithBookingOperations
{
    protected function makeBookingOperationsSectionForEdit(): Section
    {
        return Section::make(__('Front desk & payments'))
            ->description(__('Quick payment and booking actions.'))
            ->visible(fn (): bool => $this->getRecord() instanceof Booking && ! $this->getRecord()->trashed())
            ->schema([
                Text::make('')
                    ->content(fn (): HtmlString => BookingAdminGuidance::operationsSummaryHtml($this->getRecord()))
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('bookingOpSpecialDiscount')
                        ->label(function (): string {
                            $record = $this->getRecord();

                            return $record instanceof Booking && BookingSpecialDiscount::hasDiscount($record)
                                ? __('Update special discount')
                                : __('Apply special discount');
                        })
                        ->icon('heroicon-o-tag')
                        ->color('gray')
                        ->visible(function (): bool {
                            $record = $this->getRecord();
                            if (! $record instanceof Booking) {
                                return false;
                            }

                            return BookingSpecialDiscount::assessCanMutate($record, auth()->user())['allowed'];
                        })
                        ->modalHeading(__('Special discount'))
                        ->modalDescription(__('Apply a manual discount with a reason so it appears in audit logs and revenue reporting.'))
                        ->form(function (): array {
                            /** @var Booking|null $record */
                            $record = $this->getRecord() instanceof Booking ? $this->getRecord() : null;
                            $type = $record?->special_discount_type ?: BookingSpecialDiscount::TYPE_FIXED;
                            $value = $record?->special_discount_value ?: null;
                            $target = $record?->special_discount_target ?: BookingSpecialDiscount::TARGET_TOTAL;

                            return [
                                Select::make('type')
                                    ->label(__('Discount type'))
                                    ->options([
                                        BookingSpecialDiscount::TYPE_PERCENT => __('Percent (%)'),
                                        BookingSpecialDiscount::TYPE_FIXED => __('Fixed amount (PHP)'),
                                    ])
                                    ->default($type)
                                    ->live(),
                                Select::make('target')
                                    ->label(__('Discount applies to'))
                                    ->options(fn (): array => $record instanceof Booking
                                        ? BookingSpecialDiscount::targetOptionsForBooking($record)
                                        : [BookingSpecialDiscount::TARGET_TOTAL => __('Grand total (room + venue)')])
                                    ->default($target)
                                    ->required()
                                    ->native(false)
                                    ->visible(fn (): bool => $record instanceof Booking && count(BookingSpecialDiscount::targetOptionsForBooking($record)) > 1),
                                TextInput::make('value')
                                    ->label(__('Discount value'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->default($value)
                                    ->live()
                                    ->helperText(__('Enter the peso amount to deduct.')),
                                Select::make('reason_code')
                                    ->label(__('Reason'))
                                    ->options([
                                        'relative' => __('Relative / Friends'),
                                        'service_recovery' => __('Service recovery'),
                                        'vip' => __('VIP'),
                                        'promo_match' => __('Promo match'),
                                        'other' => __('Other'),
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->default($record?->special_discount_reason_code)
                                    ->live(),
                                Textarea::make('note')
                                    ->label(__('Note'))
                                    ->rows(3)
                                    ->default($record?->special_discount_note)
                                    ->required(fn ($get): bool => (string) $get('reason_code') === 'other')
                                    ->helperText(__('Required when Reason is "Other". Keep it short and specific.')),
                                Placeholder::make('preview')
                                    ->label(__('Revenue impact'))
                                    ->content(function ($get) use ($record): string {
                                        if (! $record instanceof Booking) {
                                            return '—';
                                        }

                                        $type = (string) ($get('type') ?? '');
                                        $target = (string) ($get('target') ?? ($record->special_discount_target ?: BookingSpecialDiscount::TARGET_TOTAL));
                                        $value = (float) ($get('value') ?? 0);
                                        if ($type === '' || $value <= 0) {
                                            $gross = BookingSpecialDiscount::grossTotal($record);

                                            return 'Gross ₱'.number_format($gross, 2).' → Net ₱'.number_format((float) $record->total_price, 2);
                                        }

                                        $p = BookingSpecialDiscount::preview($record, $type, $value, $target);

                                        return 'Gross ₱'.number_format($p['gross'], 2)
                                            .' → Net ₱'.number_format($p['net'], 2)
                                            .' (Discount ₱'.number_format($p['discount'], 2)
                                            .' from ₱'.number_format((float) ($p['discountableGross'] ?? $p['gross']), 2).')';
                                    }),
                            ];
                        })
                        ->action(function (array $data): void {
                            $record = $this->getRecord();
                            if (! $record instanceof Booking) {
                                return;
                            }

                            try {
                                BookingSpecialDiscount::apply(
                                    booking: $record,
                                    type: (string) ($data['type'] ?? ''),
                                    value: (float) ($data['value'] ?? 0),
                                    target: isset($data['target']) ? (string) $data['target'] : null,
                                    reasonCode: isset($data['reason_code']) ? (string) $data['reason_code'] : null,
                                    note: isset($data['note']) ? (string) $data['note'] : null,
                                    actor: auth()->user(),
                                );
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title(__('Cannot apply discount'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }

                            Notification::make()
                                ->title(__('Special discount saved.'))
                                ->success()
                                ->send();

                            $this->afterBookingLifecycleMutation();
                        }),
                    Action::make('bookingOpRemoveSpecialDiscount')
                        ->label(__('Remove discount'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Remove special discount?'))
                        ->modalDescription(__('Restores the booking total to its original gross amount. This will be logged.'))
                        ->visible(function (): bool {
                            $record = $this->getRecord();
                            if (! $record instanceof Booking) {
                                return false;
                            }
                            if (! BookingSpecialDiscount::hasDiscount($record)) {
                                return false;
                            }

                            return BookingSpecialDiscount::assessCanMutate($record, auth()->user())['allowed'];
                        })
                        ->action(function (): void {
                            $record = $this->getRecord();
                            if (! $record instanceof Booking) {
                                return;
                            }

                            try {
                                BookingSpecialDiscount::remove($record, auth()->user());
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title(__('Cannot remove discount'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }

                            Notification::make()
                                ->title(__('Special discount removed.'))
                                ->success()
                                ->send();

                            $this->afterBookingLifecycleMutation();
                        }),
                    Action::make('bookingOpPayBalance')
                        ->label(__('Settle remaining balance'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(__('Mark booking as fully paid?'))
                        ->modalDescription(__('Records one payment for the full remaining balance and sets payment to Paid. For partial cash amounts, use Payments instead.'))
                        ->modalSubmitActionLabel(__('Yes, mark as paid'))
                        ->successNotificationTitle(__('Remaining balance recorded. Booking is now paid.'))
                        ->visible(fn (): bool => $this->shouldShowPayBalanceForRecord())
                        ->disabled(fn (): bool => $this->shouldShowPayBalanceForRecord() && ! BookingFullBalancePayment::assess($this->getRecord())['allowed'])
                        ->tooltip(fn (): ?string => $this->payBalanceBlockedTooltipForRecord())
                        ->action(function (): void {
                            $this->runBookingPayBalance();
                        }),
                    Action::make('bookingOpMarkRefundCompleted')
                        ->label(__('Mark refund completed'))
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('Confirm refund completion?'))
                        ->modalDescription(fn (): string => $this->getRecord() instanceof Booking
                            ? CancellationPolicy::adminMarkRefundCompletedModalBody($this->getRecord())
                            : '')
                        ->visible(fn (): bool => $this->shouldOfferMarkRefundCompletedForRecord())
                        ->action(function (): void {
                            $this->runMarkRefundCompleted();
                        }),
                    Action::make('bookingOpCheckIn')
                        ->label(__('Check in guest'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('Check in this guest?'))
                        ->modalDescription(__('Sets stay status to Occupied (guest is on site).'))
                        ->visible(fn (): bool => $this->record instanceof Booking && BookingCheckInEligibility::assess($this->record)['allowed'])
                        ->action(function (): void {
                            $this->runBookingCheckIn();
                        }),
                    CheckoutBookingAction::makeForRecordCallbacks(
                        'bookingOpComplete',
                        fn () => $this->getRecord(),
                        function (Booking $booking) {
                            $this->afterBookingLifecycleMutation();
                        },
                    ),
                    Action::make('bookingOpMarkDamageSettled')
                        ->label(__('Mark damage settled'))
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->modalHeading(__('Mark damage/loss claim as settled?'))
                        ->form([
                            Textarea::make('notes')
                                ->label(__('Settlement notes'))
                                ->rows(3)
                                ->placeholder(__('Optional accounting note or OR/reference number.')),
                        ])
                        ->visible(fn (): bool => $this->record instanceof Booking
                            && ! $this->record->trashed()
                            && (string) $this->record->damage_settlement_status === Booking::DAMAGE_SETTLEMENT_STATUS_PENDING)
                        ->action(function (array $data): void {
                            $this->runMarkDamageSettled(isset($data['notes']) ? (string) $data['notes'] : null);
                        }),
                    Action::make('bookingOpCancel')
                        ->label(__('Cancel booking'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Cancel this booking?'))
                        ->visible(fn (): bool => $this->record instanceof Booking
                            && ! $this->record->trashed()
                            && ! in_array($this->record->booking_status, [
                                Booking::BOOKING_STATUS_CANCELLED,
                                Booking::BOOKING_STATUS_COMPLETED,
                                Booking::BOOKING_STATUS_FLAGGED,
                            ], true))
                        ->action(function (): void {
                            $this->runBookingCancel();
                        }),
                ])->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed()
            ->persistCollapsed()
            ->id('booking-operations-panel-edit')
            ->columnSpanFull();
    }

    protected function makeBookingOperationsSectionForView(): Section
    {
        return Section::make(__('Front desk & payments'))
            ->description(__('Quick payment and booking status summary.'))
            ->visible(fn (): bool => $this->getRecord() instanceof Booking && ! $this->getRecord()->trashed())
            ->schema([
                Text::make('')
                    ->content(fn (): HtmlString => BookingAdminGuidance::operationsSummaryHtml($this->getRecord()))
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->persistCollapsed()
            ->id('booking-operations-panel-view')
            ->columnSpanFull();
    }

    /**
     * Header actions for View booking (form is read-only; actions cannot live inside disabled form).
     *
     * @return array<Action>
     */
    protected function bookingLifecycleHeaderActionsForView(): array
    {
        return [
            $this->makePayBalanceHeaderAction(),
            Action::make('viewBookingMarkRefundCompleted')
                ->label(__('Mark refund completed'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Confirm refund completion?'))
                ->modalDescription(fn (): string => $this->getRecord() instanceof Booking
                    ? CancellationPolicy::adminMarkRefundCompletedModalBody($this->getRecord())
                    : '')
                ->visible(fn (): bool => $this->shouldOfferMarkRefundCompletedForRecord())
                ->action(function (): void {
                    $this->runMarkRefundCompleted();
                }),
            Action::make('viewBookingSpecialDiscount')
                ->label(function (): string {
                    $record = $this->getRecord();

                    return $record instanceof Booking && BookingSpecialDiscount::hasDiscount($record)
                        ? __('Update discount')
                        : __('Apply discount');
                })
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    if (! $record instanceof Booking) {
                        return false;
                    }

                    return BookingSpecialDiscount::assessCanMutate($record, auth()->user())['allowed'];
                })
                ->modalHeading(__('Special discount'))
                ->modalDescription(__('Apply a manual discount with a reason so it appears in audit logs and revenue reporting.'))
                ->form(function (): array {
                    /** @var Booking|null $record */
                    $record = $this->getRecord() instanceof Booking ? $this->getRecord() : null;
                    $type = $record?->special_discount_type ?: BookingSpecialDiscount::TYPE_FIXED;
                    $value = $record?->special_discount_value ?: null;
                    $target = $record?->special_discount_target ?: BookingSpecialDiscount::TARGET_TOTAL;

                    return [
                        Select::make('type')
                            ->label(__('Discount type'))
                            ->options([
                                BookingSpecialDiscount::TYPE_PERCENT => __('Percent (%)'),
                                BookingSpecialDiscount::TYPE_FIXED => __('Fixed amount (PHP)'),
                            ])
                            ->default($type)
                            ->live(),
                        Select::make('target')
                            ->label(__('Discount applies to'))
                            ->options(fn (): array => $record instanceof Booking
                                ? BookingSpecialDiscount::targetOptionsForBooking($record)
                                : [BookingSpecialDiscount::TARGET_TOTAL => __('Grand total (room + venue)')])
                            ->default($target)
                            ->required()
                            ->native(false)
                            ->visible(fn (): bool => $record instanceof Booking && count(BookingSpecialDiscount::targetOptionsForBooking($record)) > 1),
                        TextInput::make('value')
                            ->label(__('Discount value'))
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->default($value)
                            ->live()
                            ->helperText(__('Enter the peso amount to deduct.')),
                        Select::make('reason_code')
                            ->label(__('Reason'))
                            ->options([
                                'relative' => __('Relative / Friends'),
                                'service_recovery' => __('Service recovery'),
                                'vip' => __('VIP'),
                                'promo_match' => __('Promo match'),
                                'other' => __('Other'),
                            ])
                            ->native(false)
                            ->required()
                            ->default($record?->special_discount_reason_code)
                            ->live(),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(3)
                            ->default($record?->special_discount_note)
                            ->required(fn ($get): bool => (string) $get('reason_code') === 'other')
                            ->helperText(__('Required when Reason is "Other". Keep it short and specific.')),
                        Placeholder::make('preview')
                            ->label(__('Revenue impact'))
                            ->content(function ($get) use ($record): string {
                                if (! $record instanceof Booking) {
                                    return '—';
                                }

                                $type = (string) ($get('type') ?? '');
                                $target = (string) ($get('target') ?? ($record->special_discount_target ?: BookingSpecialDiscount::TARGET_TOTAL));
                                $value = (float) ($get('value') ?? 0);
                                if ($type === '' || $value <= 0) {
                                    $gross = BookingSpecialDiscount::grossTotal($record);

                                    return 'Gross ₱'.number_format($gross, 2).' → Net ₱'.number_format((float) $record->total_price, 2);
                                }

                                $p = BookingSpecialDiscount::preview($record, $type, $value, $target);

                                return 'Gross ₱'.number_format($p['gross'], 2)
                                    .' → Net ₱'.number_format($p['net'], 2)
                                    .' (Discount ₱'.number_format($p['discount'], 2)
                                    .' from ₱'.number_format((float) ($p['discountableGross'] ?? $p['gross']), 2).')';
                            }),
                    ];
                })
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    if (! $record instanceof Booking) {
                        return;
                    }

                    try {
                        BookingSpecialDiscount::apply(
                            booking: $record,
                            type: (string) ($data['type'] ?? ''),
                            value: (float) ($data['value'] ?? 0),
                            target: isset($data['target']) ? (string) $data['target'] : null,
                            reasonCode: isset($data['reason_code']) ? (string) $data['reason_code'] : null,
                            note: isset($data['note']) ? (string) $data['note'] : null,
                            actor: auth()->user(),
                        );
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title(__('Cannot apply discount'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        throw new Halt;
                    }

                    Notification::make()
                        ->title(__('Special discount saved.'))
                        ->success()
                        ->send();

                    $this->afterBookingLifecycleMutation();
                }),
            Action::make('viewBookingCheckIn')
                ->label(__('Check in guest'))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Check in this guest?'))
                ->modalDescription(__('Sets status to Occupied (guest is on site).'))
                ->visible(fn (): bool => $this->record instanceof Booking && BookingCheckInEligibility::assess($this->record)['allowed'])
                ->action(function (): void {
                    $this->runBookingCheckIn();
                }),
            CheckoutBookingAction::makeForRecordCallbacks(
                'viewBookingComplete',
                fn () => $this->getRecord(),
                function (Booking $booking) {
                    $this->afterBookingLifecycleMutation();
                },
            ),
            Action::make('viewBookingMarkDamageSettled')
                ->label(__('Mark damage settled'))
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->modalHeading(__('Mark damage/loss claim as settled?'))
                ->form([
                    Textarea::make('notes')
                        ->label(__('Settlement notes'))
                        ->rows(3)
                        ->placeholder(__('Optional accounting note or OR/reference number.')),
                ])
                ->visible(fn (): bool => $this->record instanceof Booking
                    && (string) $this->record->damage_settlement_status === Booking::DAMAGE_SETTLEMENT_STATUS_PENDING)
                ->action(function (array $data): void {
                    $this->runMarkDamageSettled(isset($data['notes']) ? (string) $data['notes'] : null);
                }),
            Action::make('viewBookingCancel')
                ->label(__('Cancel booking'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Cancel this booking?'))
                ->visible(fn (): bool => $this->record instanceof Booking
                    && ! in_array($this->record->booking_status, [
                        Booking::BOOKING_STATUS_CANCELLED,
                        Booking::BOOKING_STATUS_COMPLETED,
                        Booking::BOOKING_STATUS_FLAGGED,
                    ], true))
                ->action(function (): void {
                    $this->runBookingCancel();
                }),
        ];
    }

    protected function shouldShowPayBalanceForRecord(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking || $record->trashed()) {
            return false;
        }

        if ($record->payment_status === Booking::PAYMENT_STATUS_PAID
            || in_array($record->booking_status, [
                Booking::BOOKING_STATUS_CANCELLED,
                Booking::BOOKING_STATUS_COMPLETED,
                Booking::BOOKING_STATUS_FLAGGED,
            ], true)) {
            return false;
        }

        return (float) $record->balance > 0.009;
    }

    protected function payBalanceBlockedTooltipForRecord(): ?string
    {
        if (! $this->shouldShowPayBalanceForRecord()) {
            return null;
        }

        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return null;
        }

        $assessment = BookingFullBalancePayment::assess($record);
        if ($assessment['allowed']) {
            return null;
        }

        return $assessment['message']
            ?? match ($assessment['reason']) {
                BookingFullBalancePayment::REASON_NO_BALANCE => __('No remaining balance.'),
                default => __('This booking cannot be marked as paid yet.'),
            };
    }

    protected function shouldOfferMarkRefundCompletedForRecord(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking || $record->trashed()) {
            return false;
        }

        return in_array($record->booking_status, [
            Booking::BOOKING_STATUS_RESCHEDULED,
            Booking::BOOKING_STATUS_CANCELLED,
        ], true)
            && $record->payment_status === Booking::PAYMENT_STATUS_REFUND_PENDING;
    }

    public function runMarkRefundCompleted(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking || ! $this->shouldOfferMarkRefundCompletedForRecord()) {
            return;
        }

        $record->update([
            'payment_status' => Booking::PAYMENT_STATUS_REFUNDED,
        ]);

        Notification::make()
            ->title(__('Refund marked as completed.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingPayBalance(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingFullBalancePayment::record($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot mark as paid'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingCheckIn(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::checkIn($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot check in'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking checked in.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingComplete(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::complete($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot complete'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking marked as completed.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingCancel(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::cancel($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot cancel'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking cancelled.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runMarkDamageSettled(?string $notes = null): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        if ((string) $record->damage_settlement_status !== Booking::DAMAGE_SETTLEMENT_STATUS_PENDING) {
            return;
        }

        BookingDamageSettlement::markSettled($record, $notes, auth()->user());

        Notification::make()
            ->title(__('Damage settlement marked as settled.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    protected function afterBookingLifecycleMutation(): void
    {
        $this->record->refresh();

        if ($this->record instanceof Booking) {
            $this->redirect(BookingResource::calendarUrlForBooking($this->record));

            return;
        }

        if (method_exists($this, 'fillForm')) {
            $this->fillForm();
        }
    }
}
