<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Normalized guest identity matching (name + contact; email is matched separately in queries).
 */
final class GuestIdentity
{
    public static function normalizeNamePart(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized;
    }

    /**
     * @param  array{first_name?: string|null, middle_name?: string|null, last_name?: string|null}  $parts
     */
    public static function nameIdentityKey(array $parts): string
    {
        return implode("\0", [
            self::normalizeNamePart($parts['first_name'] ?? null),
            self::normalizeNamePart($parts['middle_name'] ?? null),
            self::normalizeNamePart($parts['last_name'] ?? null),
        ]);
    }

    public static function fullNameFromParts(?string $first, ?string $middle, ?string $last): string
    {
        $first = trim((string) ($first ?? ''));
        $middle = trim((string) ($middle ?? ''));
        $last = trim((string) ($last ?? ''));

        $segments = array_values(array_filter([$first, $middle, $last], fn (string $part): bool => $part !== ''));

        return $segments !== [] ? implode(' ', $segments) : '';
    }

    /**
     * @param  array{first_name?: string|null, middle_name?: string|null, last_name?: string|null}  $validated
     */
    public static function normalizeContact(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    public static function guestMatchesNameIdentity(Guest $guest, array $validated): bool
    {
        return self::nameIdentityKey([
            'first_name' => $guest->first_name,
            'middle_name' => $guest->middle_name,
            'last_name' => $guest->last_name,
        ]) === self::nameIdentityKey([
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
        ]);
    }

    public static function guestMatchesContactIdentity(Guest $guest, array $validated): bool
    {
        return self::normalizeContact($guest->contact_num) === self::normalizeContact(
            isset($validated['contact_num']) ? (string) $validated['contact_num'] : null,
        );
    }

    /**
     * Reuse a guest profile only when name and contact number match exactly (email matched upstream).
     *
     * @param  array<string, mixed>  $validated
     */
    public static function guestMatchesFullIdentity(Guest $guest, array $validated): bool
    {
        return self::guestMatchesNameIdentity($guest, $validated)
            && self::guestMatchesContactIdentity($guest, $validated);
    }

    public static function guestProfileNameKey(Guest $guest): string
    {
        return self::nameIdentityKey([
            'first_name' => $guest->first_name,
            'middle_name' => $guest->middle_name,
            'last_name' => $guest->last_name,
        ]);
    }

    public static function normalizedEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public static function snapshotMatchesGuestProfile(Booking $booking, Guest $guest): bool
    {
        $nameKey = self::guestProfileNameKey($guest);
        $email = self::normalizedEmail($guest->email);
        $contact = self::normalizeContact($guest->contact_num);

        $snapshotNameKey = self::nameIdentityKey(self::namePartsFromSnapshot($booking->guest_name_snapshot));

        return $snapshotNameKey === $nameKey
            && self::normalizedEmail($booking->guest_email_snapshot) === $email
            && self::normalizeContact($booking->guest_contact_snapshot) === $contact;
    }

    /**
     * Limit booking history to rows captured with the same guest name, email, and contact.
     */
    public static function applyMatchingSnapshotScope(Builder $query, Guest $guest): Builder
    {
        $bookingIds = Booking::query()
            ->where('guest_id', $guest->id)
            ->get([
                'id',
                'guest_name_snapshot',
                'guest_email_snapshot',
                'guest_contact_snapshot',
            ])
            ->filter(fn (Booking $booking): bool => self::snapshotMatchesGuestProfile($booking, $guest))
            ->pluck('id');

        if ($bookingIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $bookingIds);
    }

    /**
     * @return array{first_name: string, middle_name: string|null, last_name: string}
     */
    private static function namePartsFromSnapshot(?string $snapshot): array
    {
        $snapshot = trim((string) $snapshot);
        if ($snapshot === '') {
            return ['first_name' => '', 'middle_name' => null, 'last_name' => ''];
        }

        $parts = preg_split('/\s+/', $snapshot) ?: [];
        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => ''];
        }

        return [
            'first_name' => $parts[0],
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last_name' => $parts[count($parts) - 1],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function addressSnapshotFromValidated(array $validated): ?string
    {
        $parts = array_values(array_filter([
            $validated['barangay'] ?? null,
            $validated['municipality'] ?? null,
            $validated['province'] ?? null,
            $validated['region'] ?? null,
            $validated['country'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== ''));

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{guest_name_snapshot: string, guest_email_snapshot: string, guest_contact_snapshot: string|null, guest_address_snapshot: string|null}
     */
    public static function bookingSnapshotAttributes(array $validated): array
    {
        $email = strtolower(trim((string) ($validated['email'] ?? '')));

        return [
            'guest_name_snapshot' => self::fullNameFromParts(
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['last_name'] ?? null,
            ),
            'guest_email_snapshot' => $email,
            'guest_contact_snapshot' => isset($validated['contact_num'])
                ? (string) $validated['contact_num']
                : null,
            'guest_address_snapshot' => self::addressSnapshotFromValidated($validated),
        ];
    }
}
