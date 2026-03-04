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

    protected function getHeaderActions(): array
    {
        return [
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
                        ->label('')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, $livewire): void {
                            $payload = $state;

                            if (!$payload) {
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

                            if (!$booking) {
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
}
