# Booking Payments Management

## Overview
The Booking Payments feature introduces a robust, native workflow within the Filament admin panel to manage, track, and record cash payments directly against guest bookings. Providing a streamlined interface, it enables staff to efficiently handle partial installments, record final balances, and seamlessly monitor booking progress.

---

## 🏗 Schema & Structure

### `payments` Table
A new migration was created strictly for payment tracking.
- `id` (Primary Key)
- `booking_id` (Foreign Key natively cascading on delete)
- `total_amount` (Integer): The booking's total cost explicitly logged at the time of the transaction.
- `partial_amount` (Integer): The actual cash amount received in this specific transaction.
- `is_fullypaid` (Boolean): A flag denoting whether this specific transaction achieved full payment. 
- `created_at` & `updated_at` (Timestamps).

### Models & Helpers
#### `Payment` Model
- Explicitly protects `booking_id`, `total_amount`, `partial_amount`, and `is_fullypaid` via `$fillable`.
- Types perfectly auto-cast (amounts to integers, fullypaid to boolean).

#### `Booking` Model Extensions
The `Booking` model has been enriched with essential helper traits to provide immediate access to financial summaries without querying manually:
1. **Relationship**: `payments()` -> Returns all logged cash installments.
2. **`getTotalPaidAttribute()` ($booking->total_paid)**: Yields the combined sum of all `partial_amount`s assigned to the booking. 
3. **`getBalanceAttribute()` ($booking->balance)**: Dynamically calculates the unpaid remainder via `max(0, $this->total_price - $this->total_paid)`.

---

## 🎨 Filament Admin Features

### 1. The `PaymentsRelationManager`
Operating securely within the `BookingResource`, the relation manager provides staff the ability to visualize payment history and ingest new cash transactions.
- **Form Automation**: Staff are relieved of manual calculations. 
  - `total_amount` is inherently pre-filled with the booking's `total_price` and disabled to prevent tampering.
  - `partial_amount` bounds users dynamically; they cannot overpay beyond the booking's `max(balance)`.
- **Status Hooks**: Utilizing Filament's `after()` lifecycle hook on the `CreateAction`, creating a new cash transaction prompts an automatic recalculation. If `$booking->total_paid >= $booking->total_price`, the booking status automatically flips to universally recognized **`Booking::STATUS_PAID`**.

### 2. Live Grid Overviews
The primary Bookings table has been extended to ensure staff instantly recognize unpaid tabs:
- Two new grid columns, **Paid** and **Balance**, allow fast financial skimming.
- They employ Filament's elegant `money()` formatting.

### 3. Quick-Action: "Pay Balance"
Rather than relying completely on the relationship manager hook, grid-level efficiency has been maintained:
- **Visibility Control**: An action specifically titled "Pay Balance" exists on bookings. It intelligently hides entirely if `$record->balance == 0`.
- **Automated Logging**: Triggering it seamlessly auto-generates a subsequent payment log absorbing the exact remaining balance and finalizing the `status` string to **Paid**, accelerating check-out routines tremendously. 

---

## 🚀 Workflows Provided

**Scenario 1: The Guest Pays in Partial Installments:**
1. Staff locate the booking, navigating into the `View`/`Edit` mode.
2. Beneath the main details, the `Payments` block resides. 
3. Select `Add Payment` — inputting only the physical cash received layout. (Auto form limitations prevent accidental extreme numbers).
4. The remaining balance immediately recalculates across the dashboard.

**Scenario 2: The Guest Walk-In / Finalizes their Bill:**
1. Upon interacting with the guest, staff instantly detect their remaining Balance directly from the primary `Bookings List`.
2. Assuming the guest is paying off the entirety cleanly, staff execute the native table-action **Pay Balance**.
3. The booking flips definitively to Paid, natively logging the backend transaction perfectly without routing them deep into views. 

---

## 📂 Pertinent File Locations

If tweaks are required in the future, these are the modified structures:
- `database/migrations/*_create_payments_table.php`
- `app/Models/Payment.php`
- `app/Models/Booking.php`
- `app/Filament/Resources/Bookings/RelationManagers/PaymentsRelationManager.php`
- `app/Filament/Resources/Bookings/BookingResource.php` (Registered RelationManager)
- `app/Filament/Resources/Bookings/Tables/BookingsTable.php` (View grids and Quick actions)
