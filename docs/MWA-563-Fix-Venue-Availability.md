# MWA-563 — Fix venue availability always returning `available: true`

**Scope:** Backend only (`Marcelinos-Backend`). No frontend code changes were required for this fix.

## Summary

`GET /api/venues` (with `check_in` / `check_out`) was marking reserved venues as available because overlap detection skipped bookings where `bookings.venue_event_type` is `NULL`. In SQL, `NULL != 'wedding'` evaluates to unknown, so those rows matched neither the “non-wedding” nor the “wedding” branch of the conflict query.

This change treats `NULL` event types as non-wedding interval overlap and excludes admin-marked `booked` venues from `availableBetween`.

## Motivation

- Guests and staff could see venues as bookable on dates that already had a reserved booking attached via `booking_venue`.
- Reproduced locally: bookings with `booking_status = reserved`, venues on the pivot, and `venue_event_type = null` did not reduce `availableVenueIds`, so the API returned `"available": true`.

## Root cause

Venue collision logic lives in `App\Support\VenueWeddingPreparation::constrainToBookingsThatCollideWithVenueCandidateRange()`, used by:

- `Venue::scopeAvailableBetween()` (list + create-booking checks)
- `VenueController` (`bookedVenueIds` for unavailability copy)
- Filament `BookingForm::hasVenueConflicts()` / `constrainAvailableVenuesQuery()`
- `BookingDoubleBookDetector`

The query split conflicts into:

1. **Non-wedding:** `venue_event_type != 'wedding'` (standard `check_in` / `check_out` overlap)
2. **Wedding:** `venue_event_type = 'wedding'` (prep-day SQL overlap)

Bookings with **`venue_event_type IS NULL`** (common on admin “rooms + venue” creates that never set the radio) matched **neither** branch and were invisible to availability.

## High-level behavior after fix

- Reserved / occupied / completed / rescheduled bookings with venues and **`NULL` `venue_event_type`** now block the venue for overlapping date ranges (same as birthday / meeting / etc.).
- Explicit **`wedding`** bookings still use the one-day prep window before check-in.
- Venues with admin status **`booked`** are excluded from `availableBetween` (aligned with unavailability messaging in `VenueController::resolveVenueUnavailability()`).
- **`pending_verification`** bookings still do **not** block public availability (unchanged; by design until email verification).

## API contract (unchanged field names)

| Endpoint | Availability field | Notes |
|----------|-------------------|--------|
| `GET /api/venues?check_in=…&check_out=…` | `available` (boolean) | Not `availability`. |
| `GET /api/venues?is_all=1` | `available: null` | Browse mode; no date filter. |

When `available === false`, the API may also return `unavailability_code`, `unavailability_title`, `unavailability_detail`.

**Frontend reference (no changes in this ticket):**

- `client-marcelinos/src/lib/utils/booking.utils.ts` — `isVenueInventoryAvailable()` reads `available` / `is_block_date`
- `client-marcelinos/src/pages/Booking/Steps/Step1.tsx` — passes `venue.available` into `VenueCard` as `availability`

## Files modified / added

### Backend — logic

| File | Change |
|------|--------|
| `app/Support/VenueWeddingPreparation.php` | In `constrainToBookingsThatCollideWithVenueCandidateRange()`, non-wedding branch now uses `whereNull('venue_event_type') OR venue_event_type != 'wedding'` so NULL rows participate in overlap. |
| `app/Models/Venue.php` | `scopeAvailableBetween()` also excludes `status = booked` (in addition to `maintenance`). |

### Backend — consumers (unchanged code, fixed behavior)

These already call the shared helper / scope; they benefit automatically:

- `app/Http/Controllers/API/VenueController.php` — public venue list
- `app/Http/Controllers/API/BookingController.php` — create / verify availability checks
- `app/Filament/Resources/Bookings/Schemas/BookingForm.php` — admin venue picker & conflict rules
- `app/Support/BookingDoubleBookDetector.php` — double-book detection

### Backend — tests

| File | Change |
|------|--------|
| `tests/Feature/VenueWeddingPreparationTest.php` | Added `test_null_venue_event_type_still_blocks_overlapping_venue` (scope + `GET /api/venues`). |

### Frontend

**None.** The bug was entirely in backend SQL overlap rules.

## Verification performed

Example (local Herd, after fix):

```http
GET /api/venues?check_in=2026-05-17&check_out=2026-05-18&venue_event_type=wedding
```

With existing reserved bookings on venues 1 and 2 (`venue_event_type` null in DB):

- Venue 1 & 2 → `"available": false`
- Venue 3 (no conflict) → `"available": true`

## QA checklist (manual)

1. Create or locate a **reserved** booking with at least one venue on `booking_venue` and `venue_event_type` empty/null.
2. Call `GET /api/venues` with `check_in` / `check_out` covering that stay.
3. Confirm affected venues return `"available": false` and optional unavailability text (`Already reserved`, etc.).
4. Confirm a venue with no overlapping blocking booking still returns `"available": true`.
5. Confirm `pending_verification` bookings still do **not** block availability until verified (if testing guest flow).
6. In Filament, edit a booking with venues — venue dropdown should not offer venues that conflict for the selected dates.

## Recommended follow-up (data / product, optional)

- **Backfill** `bookings.venue_event_type` for existing rows that have `booking_venue` rows but `NULL` type (especially admin “rooms + venue” bookings). Pricing and prep-day rules use `BookingPricing::normalizeVenueEventType()`, which defaults null to `wedding`, but overlap now correctly blocks via the non-wedding branch regardless.
- Ensure Filament create/edit always persists `venue_event_type` when venues are selected (wizard already defaults to wedding when venues are present).

## Testing

```bash
cd Marcelinos-Backend
php artisan test --filter=VenueWeddingPreparationTest
```

Note: the full suite may fail on SQLite in-memory if migrations query `information_schema` (see MWA-564 doc). Prefer MySQL for integration tests when possible.
