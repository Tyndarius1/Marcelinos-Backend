# MWA-564 — Allow flexible room reassignment and ensure payment status recompute

Summary
- This change allows staff to reassign or change assigned rooms (including room types and quantities) on an existing booking while preserving billing integrity.
- It enforces server-side availability checks and adds atomic DB locking when syncing room assignments to avoid double-booking.
- It ensures booking `payment_status` is recomputed from monetary amounts when payments are created, deleted, or restored.

Motivation
- Staff needed the flexibility to change assigned rooms and room types without being blocked by strict room-line constraints.
- Previously, edits could cause race conditions (double-booking) or leave payment status inconsistent when payments were removed.
- Business requirement: UI-driven assignment changes must be safe, auditable, and keep financial status accurate.

High-level behavior changes
- The booking edit flow now:
  - Presents all available non-maintenance rooms for selection (falling back to assigned rooms if dates are missing).
  - Accepts arbitrary room selections (staff can change types and quantities freely).
  - Records an audit entry whenever assigned rooms change.
  - Performs an atomic `rooms()->sync(...)` inside a `DB::transaction()` while `lockForUpdate()` is applied to affected `rooms` to prevent concurrent assignments.
- Payment logic:
  - When a `Payment` is deleted, force-deleted, or restored, the booking's `payment_status` is recomputed based on `total_price` and `total_paid` amounts.
  - The Payments relation UI now computes "fully paid" by summing booking payments rather than a per-payment boolean column.

Files modified / added
- Modified: app/Filament/Resources/Bookings/Schemas/BookingForm.php
  - Filters `rooms` using availability helpers and falls back to assigned rooms or non-maintenance rooms when needed.

- Modified: app/Filament/Resources/Bookings/Pages/EditBooking.php
  - Captures pending assigned rooms from the form and, in `afterSave()`, performs an atomic sync under a DB transaction with `Room::lockForUpdate()` and re-checks availability.
  - Recomputes booking totals and updates `payment_status` where necessary.

- Modified: app/Models/Payment.php
  - `booted()` subscribes to `deleted`, `forceDeleted`, and `restored` events to recompute booking payment status from monetary amounts.

- Modified: app/Filament/Resources/Bookings/RelationManagers/PaymentsRelationManager.php
  - UI column for fully-paid updated to sum payments and display ✓/✕ appropriately.

- Added: app/Models/BookingAssignmentAudit.php
  - Stores `booking_id`, `user_id`, `previous_rooms`, `new_rooms`, and optional `reason` for staff-driven assignment changes.

- Added migration: database/migrations/*_create_booking_assignment_audits_table.php
  - Creates `booking_assignment_audits` table and required fields.

Database & Migrations
- New migration created and applied to add `booking_assignment_audits`.
- Note: The test suite in CI uses SQLite in-memory; some existing migrations and queries expect MySQL/Postgres metadata (e.g., queries referencing `information_schema`) and will fail on SQLite. See "Testing" below.

Runtime & Concurrency
- To prevent race conditions, assignment syncing is wrapped in a DB transaction and the selected `rooms` are locked with `lockForUpdate()` before final availability validation and `rooms()->sync()`.
- This reduces the window for double-booking and makes the edit flow safe under concurrent admin edits.

Auditing
- An audit entry is created before persisting changes to record the previous and new assigned room lists and the acting user. This helps trace manual overrides and supports later reconciliation.

Testing
- I ran the project's PHPUnit suite locally. The run exposed multiple failures caused by the test environment using SQLite in-memory while migration code or other authorization checks expect database metadata (`information_schema`) present in MySQL/Postgres.
- Recommended approaches:
  - Preferred: Run tests against a MySQL (or Postgres) test database. Example environment variables for a MySQL test run:

```bash
export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_DATABASE=marcelinos_test
export DB_USERNAME=root
export DB_PASSWORD=secret
php artisan migrate --env=testing
php vendor/bin/phpunit --colors=always
```

  - Alternative: Modify migrations/queries for SQLite compatibility (more invasive and risky for production parity).

QA Checklist (manual)
- Edit a booking and change assigned rooms (add, remove, change room type). Verify:
  - An audit record is created in `booking_assignment_audits`.
  - The assigned rooms are updated in the booking's `rooms` relationship.
  - No overlapping booking exists for the same room and dates (atomic lock prevented race).
  - Booking totals are recomputed server-side and displayed consistently in the UI.
- Create and then delete a `Payment` for a booking. Verify:
  - The booking's `payment_status` moves from `paid` -> `partial` or `unpaid` depending on remaining sums.
  - Restoring a deleted payment recomputes the `payment_status` back.
- Simulate concurrent edits to the same room (two admin sessions attempt to assign same room):
  - One should succeed; the other should receive a validation error stating the room is not available.

Rollback plan
- Reverting code changes is straightforward via git (revert commits).
- Database: drop `booking_assignment_audits` table and restore prior code paths if rollback is required.

Risks & Mitigations
- Risk: Tests currently failing on SQLite cause CI red flags. Mitigation: run tests on MySQL in CI or make migrations compatible with SQLite where appropriate.
- Risk: Business expects automatic billing adjustments when rooms change. Current implementation records an audit and leaves billing lines unchanged by default. Product decision required to change billing automatically.

Next recommended tasks
1. Add unit/integration tests specifically for:
   - Payment deletion/restore → assert `payment_status` transitions.
   - Room reassignment flow with concurrency → assert one writer wins and the other receives a clear validation error.
2. Apply the same atomic assignment logic to booking creation flows (e.g., `BookingCreateWizard`), to prevent double-booking at creation time.
3. Update CI to use a MySQL test DB (recommended) or refactor migrations for SQLite compatibility if CI must remain SQLite.
4. Add UI warnings or an explicit reconciliation workflow if billing should auto-update when rooms change.

References (key files)
- [app/Filament/Resources/Bookings/Schemas/BookingForm.php](app/Filament/Resources/Bookings/Schemas/BookingForm.php)
- [app/Filament/Resources/Bookings/Pages/EditBooking.php](app/Filament/Resources/Bookings/Pages/EditBooking.php)
- [app/Models/Payment.php](app/Models/Payment.php)
- [app/Models/BookingAssignmentAudit.php](app/Models/BookingAssignmentAudit.php)
- [app/Filament/Resources/Bookings/RelationManagers/PaymentsRelationManager.php](app/Filament/Resources/Bookings/RelationManagers/PaymentsRelationManager.php)

Author
- Implemented by the engineering agent; please contact the authoring developer for any policy or business-specific changes.

Document versioning
- File created: 2026-05-17
- Ticket: MWA-564


