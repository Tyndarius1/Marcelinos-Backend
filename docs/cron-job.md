# Laravel Booking Scheduler

This document describes the scheduled tasks configured in `routes/console.php` using **Laravel Scheduler**.

These tasks automatically update booking statuses based on the **current date** and **booking state**. They use **Artisan commands** and the **Booking model**.

**Laravel reference:** [Task Scheduling](https://laravel.com/docs/11.x/scheduling#scheduling-artisan-commands)

---

## Timezone

All scheduled times use **Asia/Manila (UTC+8)**.

- Application default: `config('app.timezone')` is `Asia/Manila` (overridable via `APP_TIMEZONE` in `.env`).
- Each scheduled task is explicitly set to `->timezone('Asia/Manila')`.

---

## Overview

The application uses **Laravel's task scheduling**. A single system cron job runs every minute and triggers `php artisan schedule:run`; Laravel then runs the defined tasks at the correct times.

**Definition:** `routes/console.php`

**Commands:** `app/Console/Commands/`

- `CompleteCheckoutBookings.php` → `bookings:complete-checkouts`
- `ActivateCheckinBookings.php` → `bookings:activate-checkins`
- `SendTestimonialFeedback.php` → `testimonials:send-feedback`
- `CancelPendingBookings.php` → `bookings:cancel-unpaid`

---

## Scheduled Tasks

### 1. Complete checked-out bookings

| Item     | Value                         |
| -------- | ----------------------------- |
| Command  | `bookings:complete-checkouts` |
| Schedule | Every minute (Asia/Manila)    |

**Logic:**

- Date used: `now()` (or `--before=<datetime>` when run manually; `--date=Y-m-d` uses end of that day).
- Selects bookings where:
    - `check_out <= before`
    - `status` = `occupied`
- Sets `status` to `completed`.

**Purpose:** Mark stays as completed after check-out and free the related rooms.

---

### 2. Activate check-in bookings (paid → occupied)

| Item     | Value                            |
| -------- | -------------------------------- |
| Command  | `bookings:activate-checkins`     |
| Schedule | Daily at **12:00** (Asia/Manila) |

**Logic:**

- Date used: today (Asia/Manila), or `--date=Y-m-d` when run manually.
- Selects bookings where:
    - `check_in` date = that date
    - `status` = `paid`
- Sets `status` to `occupied`.

**Purpose:** Mark paid bookings as occupied on check-in day.

---

### 3. Cancel unpaid bookings (no-show)

| Item     | Value                            |
| -------- | -------------------------------- |
| Command  | `bookings:cancel-unpaid`         |
| Schedule | Daily at **12:00** (Asia/Manila) |

**Logic:**

- Date used: today (Asia/Manila), or `--date=Y-m-d` when run manually.
- Selects bookings where:
    - `check_in` date = that date
    - `status` = `unpaid`
- Sets `status` to `cancelled`.

**Purpose:** Cancel unpaid bookings that reach the check-in date.

---

### 4. Send testimonial feedback emails

| Item     | Value                            |
| -------- | -------------------------------- |
| Command  | `testimonials:send-feedback`     |
| Schedule | Daily at **12:00** (Asia/Manila) |

**Logic:**

- Selects bookings where:
    - `status` = `completed`
    - `check_out <= (now - 1 day)` (effectively "at least 24 hours after check-out time has passed")
    - `testimonial_feedback_sent_at` is `null`
- Sends an email to the guest with a signed, expiring link to submit their testimonial.
- Marks `testimonial_feedback_sent_at` after a successful send.

---

---

## Server setup (cron)

### Option A: Single-command services (recommended on cPanel)

If you use **`php artisan services:start`** (or `./start-services.sh`) as described in **`documentation/deployment-services.md`**, the scheduler runs via `schedule:work` inside that process. You **do not** need a separate “every minute” cron for `schedule:run`. Use one **@reboot** cron to start all services (Reverb, queue, scheduler) at once.

### Option B: Traditional cron (scheduler only)

If you run Reverb and queue worker separately and do **not** use `schedule:work`, you need **one** cron entry so Laravel runs scheduled tasks every minute:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/project` with the application root (e.g. `/var/www/be-marcelinos`).

### Windows (Task Scheduler)

Run every minute:

```text
php artisan schedule:run
```

Use the full path to `php.exe` and set the working directory to the project root.

---

## Testing and debugging

### List scheduled tasks

```bash
php artisan schedule:list
```

Shows each task and its next run time (in Asia/Manila).

### Run the scheduler once (all due tasks)

```bash
php artisan schedule:run
```

### Run a single command (manual)

Useful for testing or backfills.

```bash
# Use “today” (Asia/Manila)
php artisan bookings:complete-checkouts
php artisan bookings:activate-checkins
php artisan bookings:cancel-unpaid

# Use a specific date (Y-m-d)
php artisan bookings:complete-checkouts --date=2025-02-09
php artisan bookings:activate-checkins --date=2025-02-09
php artisan bookings:cancel-unpaid --date=2025-02-09
```

### List booking-related commands

```bash
php artisan list bookings
```

---

## Optional: timezone in .env

Default is **Asia/Manila**. To make it explicit or override per environment, add to `.env`:

```env
APP_TIMEZONE=Asia/Manila
```

Example override (e.g. for tests):

```env
APP_TIMEZONE=UTC
```
