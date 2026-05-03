<?php

namespace App\Filament\Exports;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class RevenueExporter extends Exporter
{
    protected static ?string $model = Booking::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_number')
                ->label('Reference No.'),

            ExportColumn::make('guest_name')
                ->label('Guest Name')
                ->state(function (Booking $record): string {
                    try {
                        $record->loadMissing('guest');
                        $name = trim((string) ($record->guest?->full_name ?? ''));
                        return $name !== '' ? $name : '—';
                    } catch (\Throwable) {
                        return '—';
                    }
                }),

            ExportColumn::make('guest_email')
                ->label('Guest Email')
                ->state(function (Booking $record): string {
                    try {
                        $record->loadMissing('guest');
                        $email = $record->guest?->email ?? '';
                        return $email !== '' ? (string) $email : '—';
                    } catch (\Throwable) {
                        return '—';
                    }
                }),

            ExportColumn::make('check_in')
                ->label('Check-in')
                ->formatStateUsing(function (mixed $state): string {
                    if ($state === null || $state === '') {
                        return '—';
                    }
                    try {
                        return Carbon::parse($state)->format('d/m/y H:i');
                    } catch (\Throwable) {
                        return (string) $state;
                    }
                }),

            ExportColumn::make('check_out')
                ->label('Check-out')
                ->formatStateUsing(function (mixed $state): string {
                    if ($state === null || $state === '') {
                        return '—';
                    }
                    try {
                        return Carbon::parse($state)->format('d/m/y H:i');
                    } catch (\Throwable) {
                        return (string) $state;
                    }
                }),

            ExportColumn::make('no_of_days')
                ->label('Nights')
                ->formatStateUsing(fn (mixed $state): string => $state !== null && $state !== '' ? (string) (int) $state : '0'),

            ExportColumn::make('rooms')
                ->label('Rooms')
                ->state(function (Booking $record): string {
                    try {
                        $record->loadMissing([
                            'rooms.bedSpecifications',
                        ]);
                        $rooms = $record->rooms;
                        if (! $rooms || $rooms->isEmpty()) {
                            return '—';
                        }

                        return $rooms
                            ->map(fn (Room $room) => $room->adminSelectLabel())
                            ->implode(', ');
                    } catch (\Throwable) {
                        return '—';
                    }
                }),

            ExportColumn::make('venues')
                ->label('Venues')
                ->state(function (Booking $record): string {
                    try {
                        $record->loadMissing('venues');
                        $names = $record->venues?->pluck('name')->filter()->implode(', ');
                        return $names !== '' && $names !== null ? $names : '—';
                    } catch (\Throwable) {
                        return '—';
                    }
                }),

            ExportColumn::make('total_price')
                ->label('Booking Revenue (₱)')
                ->formatStateUsing(function (mixed $state): string {
                    $num = is_numeric($state) ? (float) $state : 0.0;
                    return number_format($num, 2, '.', ',');
                }),

            ExportColumn::make('damage_revenue')
                ->label('Damage Revenue (₱)')
                ->state(function (Booking $record): string {
                    $record->loadMissing('payments');
                    $damage = (float) $record->payments
                        ->where('payment_type', Payment::TYPE_DAMAGE)
                        ->sum('partial_amount');

                    return number_format($damage, 2, '.', ',');
                }),

            ExportColumn::make('total_revenue')
                ->label('Total Revenue (₱)')
                ->state(function (Booking $record): string {
                    $record->loadMissing('payments');
                    $damage = (float) $record->payments
                        ->where('payment_type', Payment::TYPE_DAMAGE)
                        ->sum('partial_amount');
                    $base = (float) ($record->total_price ?? 0);

                    return number_format($base + $damage, 2, '.', ',');
                }),

            ExportColumn::make('booking_status')
                ->label('Booking status')
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null || $state === '') {
                        return '—';
                    }

                    return (string) (Booking::bookingStatusOptions()[$state] ?? $state);
                }),

            ExportColumn::make('payment_status')
                ->label('Payment status')
                ->formatStateUsing(function (?string $state): string {
                    if ($state === null || $state === '') {
                        return '—';
                    }

                    return (string) (Booking::paymentStatusOptions()[$state] ?? $state);
                }),

            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(function (mixed $state): string {
                    if ($state === null || $state === '') {
                        return '—';
                    }
                    try {
                        return Carbon::parse($state)->format('d/m/y H:i');
                    } catch (\Throwable) {
                        return (string) $state;
                    }
                }),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->where('payment_status', Booking::PAYMENT_STATUS_PAID)
                    ->orWhere('booking_status', Booking::BOOKING_STATUS_COMPLETED);
            })
            ->with([
                'guest:id,first_name,middle_name,last_name,email',
                'rooms' => fn ($q) => $q->with(['bedSpecifications']),
                'venues:id,name',
                'payments:id,booking_id,payment_type,partial_amount',
            ]);
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
            ExportFormat::Csv,
        ];
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Revenue export completed. ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($export->getFailedRowsCount() > 0) {
            $body .= ' ' . Number::format($export->getFailedRowsCount()) . ' ' . str('row')->plural($export->getFailedRowsCount()) . ' failed.';
        }

        return $body;
    }
}
