<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\BookingInspections\BookingInspectionResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\HasBookingPayBalanceHeaderAction;
use App\Filament\Resources\Bookings\Concerns\InteractsWithBookingOperations;
use App\Models\Booking;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

class ViewBooking extends ViewRecord
{
    use HasBookingPayBalanceHeaderAction;
    use InteractsWithBookingOperations;

    protected static string $resource = BookingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof Booking) {
            BookingResource::markBookingAsViewed((int) $this->record->getKey());
        }
    }

    public function form(Schema $schema): Schema
    {
        $configured = parent::form($schema);
        $components = $configured->getComponents();

        return $configured->components([
            $this->makeBookingOperationsSectionForView(),
            $this->makeBookingInspectionSummarySection(),
            ...array_values($components),
        ]);
    }

    protected function makeBookingInspectionSummarySection(): Section
    {
        return Section::make(__('Checkout inspection'))
            ->description(__('Photo-backed inventory checklist submitted at checkout.'))
            ->visible(fn (): bool => $this->record instanceof Booking
                && $this->record->bookingInspection !== null)
            ->schema([
                Html::make(function (): View {
                    if (! $this->record instanceof Booking) {
                        return view('filament.bookings.inspection-detail', [
                            'inspection' => null,
                        ]);
                    }

                    $inspection = $this->record->bookingInspection?->loadMissing([
                        'items.photos',
                        'items.inventoryItem.room',
                        'inspectedBy',
                    ]);

                    return view('filament.bookings.inspection-detail', [
                        'inspection' => $inspection,
                        'detailUrl' => $inspection
                            ? BookingInspectionResource::getUrl('view', ['record' => $inspection])
                            : null,
                    ]);
                }),
            ])
            ->collapsible()
            ->columnSpanFull();
    }

    public function getHeading(): string
    {
        return 'Booking details';
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof Booking) {
            return null;
        }

        $guestName = $this->record->guest?->full_name ?: 'Unknown guest';
        $line = "{$this->record->reference_number} - {$guestName}";

        if ($this->record->booking_status === Booking::BOOKING_STATUS_FLAGGED) {
            return $line.' · '.__('Flagged — checkout inspection found issues');
        }

        return $line;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->bookingLifecycleHeaderActionsForView(),
            EditAction::make(),
        ];
    }
}
