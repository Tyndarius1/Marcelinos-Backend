<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Guest;
use App\Support\GuestIdentity;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Booking history (same name, email & phone)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $guest = $this->getOwnerRecord();
                if ($guest instanceof Guest) {
                    GuestIdentity::applyMatchingSnapshotScope($query, $guest);
                }
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Reference copied.'),
                TextColumn::make('guest_name_snapshot')
                    ->label('Booked as')
                    ->formatStateUsing(fn (?string $state, Booking $record): string => $state ?: ($record->guest?->full_name ?? '—'))
                    ->description(fn (Booking $record): string => 'Contact: '.($record->guest_contact_snapshot ?: ($record->guest?->contact_num ?? '—')))
                    ->searchable(),
                TextColumn::make('guest_email_snapshot')
                    ->label('Email used')
                    ->formatStateUsing(fn (?string $state, Booking $record): string => $state ?: ($record->guest?->email ?? '—'))
                    ->badge()
                    ->color(function (?string $state, Booking $record): string {
                        $email = $state ?: ($record->guest?->email ?? null);

                        return self::matchesSharedEmailPattern($email) ? 'warning' : 'gray';
                    })
                    ->description(fn (?string $state, Booking $record): string => self::matchesSharedEmailPattern($state ?: ($record->guest?->email ?? null)) ? 'Shared / placeholder email' : 'Personal email')
                    ->searchable(),
                TextColumn::make('check_in')
                    ->label('Check-in')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                TextColumn::make('check_out')
                    ->label('Check-out')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                TextColumn::make('booking_status')
                    ->label('Booking status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Booking::bookingStatusOptions()[$state ?? ''] ?? ($state ?? '—')),
                TextColumn::make('payment_status')
                    ->label('Payment status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Booking::paymentStatusOptions()[$state ?? ''] ?? ($state ?? '—')),
                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Booked on')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                TextColumn::make('guest_address_snapshot')
                    ->label('Address at booking')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? (string) $state : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('shared_email_only')
                    ->label('Shared email only')
                    ->query(fn (Builder $query): Builder => self::applySharedEmailFilter($query)),
                Filter::make('with_address_snapshot')
                    ->label('With address snapshot')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('guest_address_snapshot')->where('guest_address_snapshot', '!=', '')),
            ])
            ->recordActions([
                Action::make('viewBooking')
                    ->label('View booking')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record])),
            ]);
    }

    private static function applySharedEmailFilter(Builder $query): Builder
    {
        $patterns = self::sharedEmailPatterns();
        if ($patterns === []) {
            // Keep filter harmless when no pattern is configured.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($patterns): void {
            foreach ($patterns as $pattern) {
                if (str_starts_with($pattern, '@')) {
                    $inner->orWhere('guest_email_snapshot', 'like', '%'.$pattern);

                    continue;
                }

                $inner->orWhere('guest_email_snapshot', '=', $pattern);
            }
        });
    }

    private static function matchesSharedEmailPattern(?string $email): bool
    {
        $normalizedEmail = strtolower(trim((string) $email));
        if ($normalizedEmail === '') {
            return false;
        }

        foreach (self::sharedEmailPatterns() as $pattern) {
            if (str_starts_with($pattern, '@')) {
                if (str_ends_with($normalizedEmail, $pattern)) {
                    return true;
                }

                continue;
            }

            if ($normalizedEmail === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function sharedEmailPatterns(): array
    {
        $patternsRaw = trim((string) env('GUEST_SHARED_EMAIL_PATTERNS', ''));
        if ($patternsRaw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $patternsRaw),
        )));
    }
}

