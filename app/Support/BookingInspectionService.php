<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingInspection;
use App\Models\InspectionItem;
use App\Models\InspectionItemPhoto;
use App\Models\RoomInventoryItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Checkout room inventory inspection: collect template rows, validate, persist, and finalize stay status.
 */
final class BookingInspectionService
{
    public static function bookingNeedsInventoryInspection(Booking $booking): bool
    {
        if (! $booking->exists) {
            return false;
        }

        $booking->loadMissing('rooms');
        if ($booking->rooms->isEmpty()) {
            return false;
        }

        $roomIds = $booking->rooms->pluck('id')->all();

        return RoomInventoryItem::query()->whereIn('room_id', $roomIds)->exists();
    }

    /**
    * @return list<array{inventory_item_id: int, item_name: string, quantity: int, price: string, room_name: string, status: string|null, remarks: string|null, photos: array<int, mixed>}>
     */
    public static function defaultFormItems(Booking $booking): array
    {
        $booking->loadMissing('rooms');
        if ($booking->rooms->isEmpty()) {
            return [];
        }

        $roomIds = $booking->rooms->pluck('id')->all();

        return RoomInventoryItem::query()
            ->whereIn('room_id', $roomIds)
            ->with(['room:id,name'])
            ->orderBy('room_id')
            ->orderBy('id')
            ->get()
            ->map(fn (RoomInventoryItem $row): array => [
                'inventory_item_id' => (int) $row->id,
                'item_name' => (string) $row->item_name,
                'quantity' => (int) $row->quantity,
                'price' => number_format((float) $row->price, 2, '.', ''),
                'room_name' => (string) ($row->room?->name ?? '—'),
                'status' => null,
                'remarks' => null,
                'photos' => [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     *
     * @throws \InvalidArgumentException
     */
    /**
     * @param  list<array<string, mixed>>  $rows
     *
     * @throws \InvalidArgumentException
     */
    public static function assertInventoryRowsMatchBooking(Booking $booking, array $rows): void
    {
        $booking->loadMissing('rooms');
        $template = self::defaultFormItems($booking);
        $expectedIds = collect($template)->pluck('inventory_item_id')->sort()->values()->all();
        $submittedIds = collect($rows)->map(fn (array $r): int => (int) ($r['inventory_item_id'] ?? 0))->filter()->sort()->values()->all();

        if ($expectedIds !== $submittedIds) {
            throw new \InvalidArgumentException(__('The checklist must include every inventory item for assigned rooms. Refresh and try again.'));
        }
    }

    public static function validateFormRows(array $rows, bool $requireAtLeastOneRow): void
    {
        if ($requireAtLeastOneRow && $rows === []) {
            throw new \InvalidArgumentException(__('Add at least one inventory item to assigned rooms before checkout, or remove room assignments.'));
        }

        foreach ($rows as $i => $row) {
            $status = (string) ($row['status'] ?? '');
            if (! in_array($status, [
                InspectionItem::STATUS_OK,
                InspectionItem::STATUS_DAMAGED,
                InspectionItem::STATUS_MISSING,
            ], true)) {
                throw new \InvalidArgumentException(__('Each inventory line must have a status (row :row).', ['row' => $i + 1]));
            }

            $photos = $row['photos'] ?? [];
            if (! is_array($photos)) {
                $photos = $photos ? [$photos] : [];
            }

            if ($status === InspectionItem::STATUS_DAMAGED && $photos === []) {
                throw new \InvalidArgumentException(__('Photo evidence is required for damaged items (:item).', [
                    'item' => (string) ($row['item_name'] ?? $i + 1),
                ]));
            }
        }
    }

    /**
     * @param  array{items?: array<int, array<string, mixed>>, notes?: string|null}  $data
     */
    public static function submitAndCheckout(Booking $booking, array $data, ?User $actor = null): BookingInspection
    {
        if (! $booking->canAdminCheckout()) {
            throw new \InvalidArgumentException(__('This booking is not eligible for checkout.'));
        }

        if (! self::bookingNeedsInventoryInspection($booking)) {
            throw new \InvalidArgumentException(__('No room inventory is configured for this booking’s rooms; use the quick checkout path.'));
        }

        if ($booking->bookingInspection) {
            throw new \InvalidArgumentException(__('An inspection is already recorded for this booking.'));
        }

        $rows = $data['items'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        self::assertInventoryRowsMatchBooking($booking, $rows);

        self::validateFormRows($rows, true);

        $allOk = collect($rows)->every(fn (array $r): bool => (string) ($r['status'] ?? '') === InspectionItem::STATUS_OK);
        $inspectionStatus = $allOk
            ? BookingInspection::STATUS_CLEAR
            : BookingInspection::STATUS_WITH_ISSUES;

        $notes = isset($data['notes']) && filled($data['notes']) ? trim((string) $data['notes']) : null;

        $actorId = $actor?->id ?? auth()->id();
        if (! is_int($actorId) && ! is_numeric($actorId)) {
            throw new \InvalidArgumentException(__('You must be signed in to record an inspection.'));
        }
        $actorId = (int) $actorId;

        return DB::transaction(function () use ($booking, $rows, $inspectionStatus, $notes, $actorId): BookingInspection {
            $inspection = BookingInspection::query()->create([
                'booking_id' => (int) $booking->id,
                'inspected_by' => $actorId,
                'status' => $inspectionStatus,
                'notes' => $notes,
            ]);

            foreach ($rows as $row) {
                $invId = (int) ($row['inventory_item_id'] ?? 0);
                if ($invId <= 0) {
                    continue;
                }

                $item = InspectionItem::query()->create([
                    'inspection_id' => (int) $inspection->id,
                    'inventory_item_id' => $invId,
                    'status' => (string) $row['status'],
                    'remarks' => filled($row['remarks'] ?? null) ? trim((string) $row['remarks']) : null,
                ]);

                $photos = $row['photos'] ?? [];
                if (! is_array($photos)) {
                    $photos = $photos ? [$photos] : [];
                }

                foreach ($photos as $file) {
                    $path = self::storePhotoFile($file);
                    if ($path !== null) {
                        InspectionItemPhoto::query()->create([
                            'inspection_item_id' => (int) $item->id,
                            'file_path' => $path,
                        ]);
                    }
                }
            }

            $targetStatus = $inspectionStatus === BookingInspection::STATUS_CLEAR
                ? Booking::BOOKING_STATUS_COMPLETED
                : Booking::BOOKING_STATUS_FLAGGED;

            $booking->update(['booking_status' => $targetStatus]);

            $actorUser = User::query()->find($actorId);
            BookingDamageSettlement::syncFromChecklist(
                $booking->fresh(['roomChecklists.items']),
                $actorUser instanceof User ? $actorUser : null,
            );

            if ($inspectionStatus === BookingInspection::STATUS_WITH_ISSUES) {
                $booking->update([
                    'has_damage_claim' => true,
                    'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
                    'damage_settlement_marked_by' => $actorId,
                    'damage_settlement_marked_at' => now(),
                ]);
            }

            self::logInspectionActivity($booking, (int) $inspection->id);

            return $inspection->fresh(['items.photos', 'items.inventoryItem']);
        });
    }

    /**
     * Checkout when no inventory template rows exist for assigned rooms (no inspection record).
     */
    public static function simpleCheckout(Booking $booking): void
    {
        if (! $booking->canAdminCheckout()) {
            throw new \InvalidArgumentException(__('This booking is not eligible for checkout.'));
        }

        if (self::bookingNeedsInventoryInspection($booking)) {
            throw new \InvalidArgumentException(__('Complete the room inventory inspection before checkout.'));
        }

        BookingLifecycleActions::completeWithoutInspectionRequirement($booking);
    }

    private static function logInspectionActivity(Booking $booking, int $inspectionId): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! in_array((string) $actor->role, ['admin', 'staff'], true)) {
            return;
        }

        ActivityLogger::log(
            category: 'booking',
            event: 'checkout_inspection_recorded',
            description: sprintf(
                '%s completed checkout inspection #%d for booking %s.',
                $actor->name,
                $inspectionId,
                $booking->reference_number,
            ),
            subject: $booking,
            meta: [
                'reference_number' => $booking->reference_number,
                'inspection_id' => $inspectionId,
                'triggered_by_user_id' => (int) $actor->id,
                'triggered_by_user_name' => (string) $actor->name,
                'booking_status' => (string) $booking->booking_status,
            ],
            userId: (int) $actor->id,
        );
    }

    private static function storePhotoFile(mixed $file): ?string
    {
        if ($file instanceof TemporaryUploadedFile) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg';
            $name = Str::uuid()->toString().'.'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'jpg');

            return $file->storeAs('inspections', $name, 'public');
        }

        if ($file instanceof UploadedFile) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg';
            $name = Str::uuid()->toString().'.'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'jpg');

            return $file->storeAs('inspections', $name, 'public');
        }

        if (is_string($file) && $file !== '') {
            $file = ltrim($file, '/');
            if (str_starts_with($file, 'inspections/')) {
                return $file;
            }

            return null;
        }

        return null;
    }
}
