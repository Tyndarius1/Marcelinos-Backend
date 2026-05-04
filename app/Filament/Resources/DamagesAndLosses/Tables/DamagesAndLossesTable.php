<?php

namespace App\Filament\Resources\DamagesAndLosses\Tables;

use App\Models\Booking;
use App\Models\RoomChecklistItem;
use App\Models\RoomChecklistTemplate;
use App\Support\BookingDamageSettlement;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DamagesAndLossesTable
{
    /**
     * @var array<string, float>|null
     */
    private static ?array $templateChargeMap = null;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roomChecklist.booking.guest.full_name')
                    ->label('guest_name')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('roomChecklist.booking.guest', function ($guestQuery) use ($search): void {
                            $guestQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('roomChecklist.booking.reference_number')
                    ->label('booking_reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roomChecklist.booking.guest.contact_num')
                    ->label('contact')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('roomChecklist.room.name')
                    ->label('room_number')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('roomChecklist.booking.damageSettlementMarker.name')
                    ->label('inspected_by')
                    ->placeholder('—'),

                TextColumn::make('roomChecklist.completed_at')
                    ->label('inspected_at')
                    ->state(fn (RoomChecklistItem $record) => $record->roomChecklist?->completed_at
                        ?? $record->roomChecklist?->booking?->damage_settlement_marked_at)
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('label')
                    ->label('item_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('issue_type')
                    ->badge()
                    ->color(fn (string $state): string => $state === RoomChecklistItem::STATUS_MISSING ? 'danger' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === RoomChecklistItem::STATUS_BROKEN ? 'damaged' : $state),

                TextColumn::make('quantity')
                    ->state(fn (RoomChecklistItem $record): int => max(1, (int) ($record->quantity ?? 1)))
                    ->label('quantity'),

                ImageColumn::make('evidence_photo_path')
                    ->label('photos')
                    ->disk('public')
                    ->square()
                    ->height(42),

                TextColumn::make('total_charge')
                    ->label('total_charge')
                    ->money('PHP')
                    ->state(function (RoomChecklistItem $record): float {
                        $amount = self::resolveItemCharge($record);
                        $quantity = max(1, (int) ($record->quantity ?? 1));

                        return $amount * $quantity;
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('charge', $direction)),

                IconColumn::make('is_chargeable')
                    ->label('is_chargeable')
                    ->boolean()
                    ->state(fn (RoomChecklistItem $record): bool => (self::resolveItemCharge($record) * max(1, (int) ($record->quantity ?? 1))) > 0),

                TextColumn::make('roomChecklist.booking.damage_settlement_status')
                    ->label('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) ($state ?? 'none')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('updateChargeAndQuantity')
                    ->label('Update charge')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->fillForm(fn (RoomChecklistItem $record): array => [
                        'charge' => self::resolveItemCharge($record),
                        'quantity' => max(1, (int) ($record->quantity ?? 1)),
                    ])
                    ->form([
                        TextInput::make('charge')
                            ->label('Charge')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (RoomChecklistItem $record, array $data): void {
                        $record->update([
                            'charge' => (string) ((float) ($data['charge'] ?? 0)),
                            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                        ]);

                        Notification::make()
                            ->title('Charge details updated')
                            ->success()
                            ->send();
                    }),
                Action::make('markAsSettled')
                    ->label('Mark as settled')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark damage/loss as settled')
                    ->modalDescription('This marks the booking damage/loss status as settled.')
                    ->visible(fn (RoomChecklistItem $record): bool => (string) ($record->roomChecklist?->booking?->damage_settlement_status ?? '') !== Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED)
                    ->action(function (RoomChecklistItem $record): void {
                        $booking = $record->roomChecklist?->booking;
                        if (! $booking instanceof Booking) {
                            Notification::make()
                                ->title('Booking not found for this row')
                                ->danger()
                                ->send();

                            return;
                        }

                        BookingDamageSettlement::markSettled($booking, notes: null, actor: auth()->user());

                        Notification::make()
                            ->title('Marked as settled')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([25, 50, 100]);
    }

    private static function parseMoneyToFloat(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value);
        if (! is_string($normalized) || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0.0;
        }

        return max(0, (float) $normalized);
    }

    private static function resolveItemCharge(RoomChecklistItem $record): float
    {
        $directCharge = self::parseMoneyToFloat((string) ($record->charge ?? '0'));
        if ($directCharge > 0) {
            return $directCharge;
        }

        $label = strtolower(trim((string) $record->label));
        if ($label === '') {
            return 0.0;
        }

        $roomType = strtolower(trim((string) ($record->roomChecklist?->room?->type ?? '')));
        $map = self::templateChargeMap();

        if ($roomType !== '' && array_key_exists("{$label}::{$roomType}", $map)) {
            return $map["{$label}::{$roomType}"];
        }

        return $map["{$label}::*"] ?? 0.0;
    }

    /**
     * @return array<string, float>
     */
    private static function templateChargeMap(): array
    {
        if (self::$templateChargeMap !== null) {
            return self::$templateChargeMap;
        }

        $templates = RoomChecklistTemplate::query()
            ->where('is_active', true)
            ->get(['label', 'default_charge', 'applicable_room_types']);

        self::$templateChargeMap = $templates
            ->reduce(function (array $carry, RoomChecklistTemplate $template): array {
                $label = strtolower(trim((string) $template->label));
                if ($label === '') {
                    return $carry;
                }

                $amount = self::parseMoneyToFloat((string) ($template->default_charge ?? '0'));
                $types = self::normalizedRoomTypes($template->applicable_room_types);

                if ($types->isEmpty()) {
                    $carry["{$label}::*"] = $amount;

                    return $carry;
                }

                foreach ($types as $type) {
                    $carry["{$label}::{$type}"] = $amount;
                }

                return $carry;
            }, []);

        return self::$templateChargeMap;
    }

    /**
     * @param  mixed  $rawTypes
     * @return Collection<int, string>
     */
    private static function normalizedRoomTypes(mixed $rawTypes): Collection
    {
        if (! is_array($rawTypes)) {
            return collect();
        }

        return collect($rawTypes)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $type): bool => $type !== '')
            ->unique()
            ->values();
    }
}
