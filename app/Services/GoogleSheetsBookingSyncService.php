<?php

namespace App\Services;

use App\Models\Booking;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Spreadsheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GoogleSheetsBookingSyncService
{
    private const HEADER = [
        'Reference Number',
        'Status',
        'Guest Name',
        'Guest Email',
        'Guest Contact',
        'Check In',
        'Check Out',
        'Rooms',
        'Venues',
        'Total Price',
        'Amount Paid',
        'Balance',
        'Payment Method',
        'Updated At',
    ];

    public function syncBooking(Booking $booking): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $spreadsheetId = $this->spreadsheetId();
        if ($spreadsheetId === '') {
            Log::warning('Google Sheets booking sync skipped: spreadsheet id is missing.');

            return;
        }

        try {
            $booking->loadMissing(['guest', 'rooms', 'venues', 'roomLines']);
            $service = $this->makeSheetsService();

            $allTabs = $this->allSheetTabs();
            $targetTab = $this->statusTabName((string) $booking->booking_status);
            $this->ensureSheetsExist($service, $spreadsheetId, $allTabs);

            $newRow = $this->buildBookingRow($booking);
            $reference = (string) $booking->reference_number;

            foreach ($allTabs as $tabName) {
                $rows = $this->readDataRows($service, $spreadsheetId, $tabName);
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row): bool => $this->referenceFromRow($row) !== $reference
                ));

                if ($tabName === $targetTab) {
                    $rows[] = $newRow;
                }

                $this->writeDataRows($service, $spreadsheetId, $tabName, $rows);
            }
        } catch (\Throwable $exception) {
            Log::error('Google Sheets booking sync failed', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
        }
    }

    public function syncAllBookings(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $spreadsheetId = $this->spreadsheetId();
        if ($spreadsheetId === '') {
            Log::warning('Google Sheets booking full sync skipped: spreadsheet id is missing.');

            return;
        }

        try {
            $service = $this->makeSheetsService();
            $allTabs = $this->allSheetTabs();
            $this->ensureSheetsExist($service, $spreadsheetId, $allTabs);

            $rowsByTab = collect($allTabs)->mapWithKeys(fn (string $tab): array => [$tab => []])->all();

            Booking::query()
                ->with(['guest', 'rooms', 'venues', 'roomLines'])
                ->orderBy('id')
                ->chunkById(200, function (Collection $bookings) use (&$rowsByTab): void {
                    foreach ($bookings as $booking) {
                        $tab = $this->statusTabName((string) $booking->booking_status);
                        $rowsByTab[$tab][] = $this->buildBookingRow($booking);
                    }
                });

            foreach ($allTabs as $tabName) {
                $this->writeDataRows($service, $spreadsheetId, $tabName, $rowsByTab[$tabName] ?? []);
            }
        } catch (\Throwable $exception) {
            Log::error('Google Sheets booking full sync failed', [
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
        }
    }

    public function removeBookingByReference(string $referenceNumber): void
    {
        if (! $this->isEnabled() || trim($referenceNumber) === '') {
            return;
        }

        $spreadsheetId = $this->spreadsheetId();
        if ($spreadsheetId === '') {
            return;
        }

        try {
            $service = $this->makeSheetsService();
            $allTabs = $this->allSheetTabs();
            $this->ensureSheetsExist($service, $spreadsheetId, $allTabs);

            foreach ($allTabs as $tabName) {
                $rows = $this->readDataRows($service, $spreadsheetId, $tabName);
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row): bool => $this->referenceFromRow($row) !== $referenceNumber
                ));
                $this->writeDataRows($service, $spreadsheetId, $tabName, $rows);
            }
        } catch (\Throwable $exception) {
            Log::error('Google Sheets booking removal failed', [
                'reference_number' => $referenceNumber,
                'error' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.google_sheets.enabled', false);
    }

    private function spreadsheetId(): string
    {
        return trim((string) config('services.google_sheets.spreadsheet_id', ''));
    }

    private function statusTabName(string $status): string
    {
        $map = (array) config('services.google_sheets.status_to_sheet', []);
        $key = strtolower(trim($status));

        return (string) ($map[$key] ?? ($map['reserved'] ?? 'Reserved'));
    }

    /**
     * @return array<int, string>
     */
    private function allSheetTabs(): array
    {
        $map = (array) config('services.google_sheets.status_to_sheet', []);

        return collect($map)
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function makeSheetsService(): GoogleSheets
    {
        $resolvedPath = storage_path('app/google-credentials.json');

        if (! is_file($resolvedPath)) {
            throw new \RuntimeException("Google Sheets credentials file not found at [{$resolvedPath}].");
        }

        $client = new GoogleClient();
        $client->setApplicationName('Marcelinos Booking Sync');
        $client->setScopes([GoogleSheets::SPREADSHEETS]);
        $client->setAuthConfig($resolvedPath);

        return new GoogleSheets($client);
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function ensureSheetsExist(GoogleSheets $service, string $spreadsheetId, array $sheetNames): void
    {
        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);

        $existing = collect($spreadsheet->getSheets())
            ->map(fn ($sheet) => (string) $sheet->getProperties()->getTitle())
            ->filter()
            ->values();

        $addSheetRequests = collect($sheetNames)
            ->filter(fn (string $name): bool => ! $existing->contains($name))
            ->map(fn (string $name): array => [
                'addSheet' => [
                    'properties' => ['title' => $name],
                ],
            ])
            ->values()
            ->all();

        if ($addSheetRequests !== []) {
            $service->spreadsheets->batchUpdate(
                $spreadsheetId,
                new BatchUpdateSpreadsheetRequest([
                    'requests' => $addSheetRequests,
                ])
            );
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readDataRows(GoogleSheets $service, string $spreadsheetId, string $sheetName): array
    {
        $response = $service->spreadsheets_values->get($spreadsheetId, "{$sheetName}!A:Z");
        $values = $response->getValues();

        if (! is_array($values) || $values === []) {
            return [];
        }

        // Skip the first row (header).
        return array_values(array_slice($values, 1));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeDataRows(GoogleSheets $service, string $spreadsheetId, string $sheetName, array $rows): void
    {
        $rows = array_map(
            fn (array $row): array => array_map(fn ($value): string => (string) $value, $row),
            $rows
        );

        $values = array_merge([self::HEADER], $rows);

        $service->spreadsheets_values->clear($spreadsheetId, "{$sheetName}!A:Z", new \Google\Service\Sheets\ClearValuesRequest());
        $service->spreadsheets_values->update(
            $spreadsheetId,
            "{$sheetName}!A1",
            new \Google\Service\Sheets\ValueRange(['values' => $values]),
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildBookingRow(Booking $booking): array
    {
        $guestName = $booking->displayGuestName() !== '—' ? $booking->displayGuestName() : '';
        $guestEmail = trim((string) ($booking->guest?->email ?? ''));
        $guestContact = trim((string) ($booking->guest?->contact_num ?? ''));
        $checkIn = $booking->check_in?->format('Y-m-d H:i:s') ?? '';
        $checkOut = $booking->check_out?->format('Y-m-d H:i:s') ?? '';
        $rooms = $booking->rooms->pluck('name')->filter()->implode(', ');
        $venues = $booking->venues->pluck('name')->filter()->implode(', ');
        $total = number_format((float) $booking->total_price, 2, '.', '');
        $amountPaid = number_format((float) $booking->total_paid, 2, '.', '');
        $balance = number_format((float) $booking->balance, 2, '.', '');
        $paymentMethod = (string) ($booking->payment_method ?? '');
        $updatedAt = $booking->updated_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');

        return [
            (string) $booking->reference_number,
            $this->compositeStatusLabel($booking),
            $guestName,
            $guestEmail,
            $guestContact,
            $checkIn,
            $checkOut,
            $rooms,
            $venues,
            $total,
            $amountPaid,
            $balance,
            $paymentMethod,
            $updatedAt,
        ];
    }

    private function compositeStatusLabel(Booking $booking): string
    {
        $stay = Booking::bookingStatusOptions()[(string) $booking->booking_status] ?? (string) $booking->booking_status;
        $pay = Booking::paymentStatusOptions()[(string) $booking->payment_status] ?? (string) $booking->payment_status;

        return "{$stay} · {$pay}";
    }

    /**
     * @param  array<int, string>  $row
     */
    private function referenceFromRow(array $row): string
    {
        return trim((string) ($row[0] ?? ''));
    }
}
