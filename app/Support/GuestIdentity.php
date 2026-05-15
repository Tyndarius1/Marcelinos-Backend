<?php

namespace App\Support;

use App\Models\Guest;

/**
 * Normalized guest name matching for email + identity reuse (not email alone).
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
