<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function getHeading(): string
    {
        return 'Bookings list';
    }

    public function getSubheading(): ?string
    {
        return 'Search, filter, and manage reservations in one place.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendarView')
                ->label('Booking Calendar')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(BookingResource::getUrl('roomCalendar')),
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

                            [$bookingId, $reference] = self::extractBookingLookupFromQr($payload);

                            $booking = null;

                            if ($bookingId) {
                                $booking = Booking::find($bookingId);
                            }

                            if (! $booking && $reference) {
                                $booking = Booking::where('reference_number', $reference)->first();
                            }

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
                ->action(fn() => null),
        ];
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private static function extractBookingLookupFromQr(string $payload): array
    {
        $cleanPayload = trim($payload);
        $cleanPayload = preg_replace('/^\xEF\xBB\xBF/', '', $cleanPayload) ?? $cleanPayload;

        $decoded = json_decode($cleanPayload, true);

        if (! is_array($decoded)) {
            $base64Decoded = base64_decode($cleanPayload, true);
            if (is_string($base64Decoded) && $base64Decoded !== '') {
                $decoded = json_decode($base64Decoded, true);
            }
        }

        $bookingId = null;
        $reference = null;

        if (is_array($decoded)) {
            $bookingId = $decoded['booking_id'] ?? $decoded['bookingId'] ?? $decoded['id'] ?? null;
            $reference = $decoded['reference_number']
                ?? $decoded['reference']
                ?? $decoded['referenceNumber']
                ?? $decoded['ref']
                ?? null;
        }

        if (is_numeric($bookingId)) {
            $bookingId = (int) $bookingId;
        } else {
            $bookingId = null;
        }

        if (! is_string($reference) || trim($reference) === '') {
            $reference = self::extractReferenceFromUrlOrText($cleanPayload);
        } else {
            $reference = trim($reference);
        }

        return [$bookingId, $reference];
    }

    private static function extractReferenceFromUrlOrText(string $payload): ?string
    {
        $query = parse_url($payload, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['reference', 'reference_number', 'ref'] as $key) {
                $value = $params[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        if (preg_match('/\bMWA-\d{4}-\d{6}\b/', $payload, $matches) === 1) {
            return $matches[0];
        }

        if (! str_starts_with($payload, '{') && ! str_starts_with($payload, '[')) {
            $trimmed = trim($payload);
            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
