# Filament panel notification sound

This document describes the in-app chime that plays when staff or admin users receive a new Filament database notification (for example, a new booking). It covers why it was built this way, how the pieces fit together, and what to adjust in production.

## Goals

- Play a short, noticeable sound when a new notification is relevant to the logged-in user.
- Keep the sound in sync with the notification bell as much as possible (avoid multi-second lag when realtime is available).
- Work even when Filament’s default broadcast path is delayed or never runs (for example, when no queue worker is processing jobs).
- Avoid double chimes when both WebSocket and UI polling update the unread count.

## Why a custom broadcast event was needed

Filament’s `Notification::make()->broadcast($user)` sends a `BroadcastNotification` that implements Laravel’s `ShouldQueue`. That means the push to Pusher (or another broadcaster) is **queued**. If `php artisan queue:work` is not running, the browser never receives that event over Echo, even though `sendToDatabase()` still saves the row and the bell updates on Livewire’s `wire:poll`.

To get an **immediate** signal in the same request lifecycle as the booking change, this project dispatches a separate event:

- **`App\Events\FilamentNotificationSound`**
- Implements **`ShouldBroadcastNow`**, so Laravel broadcasts it **without** going through the queue (still performs the HTTP call to Pusher during the request, or the configured driver’s equivalent).

The event uses the **same private channel naming** as Filament and Laravel notifications: `App.Models.User.{id}` (with dots instead of backslashes in the class name), or the value returned by `receivesBroadcastNotificationsOn()` on the user model if that method exists.

The broadcast name Echo listens for is:

- **`filament-notification.sound`** (in Echo: `.filament-notification.sound` with the leading dot).

Channel authorization is already defined in `routes/channels.php` for `App.Models.User.{id}`.

## Why it still is not “instant” (latency is normal)

Several layers add delay; none of them can be reduced to zero:

1. **Network (Pusher / Reverb / etc.)**  
   After PHP broadcasts `FilamentNotificationSound`, the driver talks to your realtime service, which then delivers over WebSockets. Expect on the order of **tens to a few hundred milliseconds**, depending on region and connectivity.

2. **Separate browser tab**  
   The booking is usually created from the **public API** or another client, while staff listen in the **Filament tab**. The sound can only run after the event reaches **that** tab’s Echo connection.

3. **Livewire `wire:poll` and background tabs (important)**  
   Livewire 3 **throttles** `wire:poll` heavily when the document tab is in the **background** unless the poll uses the **`keep-alive`** modifier (see Livewire’s `wire-poll.js`: background + missing `keep-alive` skips ~95% of poll ticks).  
   Stock Filament does not add `keep-alive`, so the **badge** (and our badge-based sound fallback) could lag until you focus the tab again.  
   This project uses **`App\Filament\Livewire\DatabaseNotifications`** and a copied Blade view that sets **`wire:poll.{interval}.keep-alive`** so polling continues in the background.

4. **Polling interval**  
   Any path that depends on refreshing the unread count is bounded by the poll interval (configured as **`1s`** in the panel providers). Worst-case delay for that path is about one interval after the database row exists.

5. **Browser autoplay**  
   Until the user has interacted with the page, `AudioContext` may stay **suspended** and the chime may not play (or may play only after a click/keypress).

## Server-side wiring

### Booking observer

`App\Observers\BookingObserver` sends Filament notifications on booking **created** and on certain **status** changes. After each:

```text
Notification::make()->...->sendToDatabase($user)->broadcast($user);
```

the observer calls:

```text
$this->dispatchNotificationSound($user);
```

which runs:

```php
event(new FilamentNotificationSound($user));
```

inside a **try/catch** that logs at debug level on failure so a misconfigured broadcaster does not break booking flows.

### Extending to other notification sources

Only booking-related notifications dispatch `FilamentNotificationSound` today. If other code paths call `Notification::make()->sendToDatabase($user)` and should also trigger the chime, dispatch the same event there (or extract a small helper) after the notification is persisted.

## Client-side wiring (Filament hook)

### Registration

`App\Providers\AppServiceProvider::boot()` calls `registerFilamentNotificationSoundHook()`, which registers a Filament render hook:

- **Hook:** `Filament\View\PanelsRenderHook::BODY_END`
- **View:** `resources/views/filament/hooks/notification-sound.blade.php`
- **Scope:** default (empty scope), so it runs on **all** Filament panels when the layout renders.

The Blade view only outputs the script when `filament()->auth()->check()` and a broadcast channel name can be resolved for the current user.

### Echo subscription

The script subscribes with:

```javascript
window.Echo.private(channel).listen('.filament-notification.sound', () => { ... playChime(); });
```

**Reliability details:**

- It listens for the native `EchoLoaded` event (same pattern Filament uses) and also calls `subscribeEcho()` immediately if `window.Echo` already exists.
- A **retry loop** runs every **75 ms** for up to **160** attempts (~12 s) so that if Echo loads slightly after the script, the listener is still attached without dispatching a synthetic `EchoLoaded` (which could duplicate Filament’s own subscriptions).

### Fallback: unread badge on the bell

If Echo or the broadcaster is unavailable, the chime can still run when the **numeric badge** on the topbar bell increases. The script:

1. Reads `.fi-topbar-database-notifications-btn .fi-badge` and parses the integer.
2. Compares to the last seen count; if it **increases**, it calls `playChime()` (subject to deduplication below).

To react as soon as Livewire updates the DOM (including after `wire:poll`), it registers:

```javascript
Livewire.hook('morph.updated', () => requestAnimationFrame(checkBadgeDelta));
```

A **300 ms** `setInterval` remains as a safety net.

**Deduplication:** If a chime was triggered from Echo within the last **4.5 seconds**, the badge path **does not** play again when the poll catches up. That avoids two beeps for one notification.

### Debouncing

`playChime()` ignores calls within **1200 ms** of the previous chime to prevent rapid duplicate triggers from unrelated UI churn.

### Audio implementation

Sound is generated with the **Web Audio API** (no audio files):

- Two short **square-wave** beeps (~2200 Hz then ~3000 Hz).
- Fast attack (~4 ms) and peak gain **0.38** for a sharp, fairly loud alert.

**Browser autoplay:** Many browsers start `AudioContext` in a **suspended** state until the user has interacted with the page. The script calls `resume()` on **click** and **keydown** (capture phase) to unlock audio when possible. If the context is still suspended when a notification arrives, the chime may be silent until after a user gesture.

## Panel configuration (latency and UX)

In **`AdminPanelProvider`** and **`StaffPanelProvider`**:

- **`databaseNotifications(true, App\Filament\Livewire\DatabaseNotifications::class, false)`** — Uses a custom Livewire component that renders `resources/views/filament/livewire/database-notifications.blade.php` so we can add **`wire:poll.{interval}.keep-alive`** (see “Why it still is not instant” above).
- **`lazyLoadedDatabaseNotifications(false)`** is applied via the third argument (`false`) on `databaseNotifications(...)`.
- **`databaseNotificationsPolling('1s')`** — Tighter poll so badge-driven updates (and the sound fallback) stay closer to realtime. Increase if this is too chatty for your environment.

## Files involved

| Area | Path |
|------|------|
| Broadcast event | `app/Events/FilamentNotificationSound.php` |
| Dispatch after booking notifications | `app/Observers/BookingObserver.php` (`dispatchNotificationSound`) |
| Render hook registration | `app/Providers/AppServiceProvider.php` (`registerFilamentNotificationSoundHook`) |
| Inline script + audio | `resources/views/filament/hooks/notification-sound.blade.php` |
| Panel tuning + custom DB notifications view | `app/Providers/Filament/AdminPanelProvider.php`, `StaffPanelProvider.php`, `app/Filament/Livewire/DatabaseNotifications.php`, `resources/views/filament/livewire/database-notifications.blade.php` |
| Private channel auth | `routes/channels.php` (`App.Models.User.{id}`) |

## Operational checklist

- **`BROADCAST_CONNECTION`** and Pusher (or Reverb, etc.) env vars must be valid for realtime delivery.
- Filament’s **`->broadcast($user)`** still benefits from a running **queue worker** for toasts and other queued notification behavior; the custom sound event is independent of that.
- After changing the Blade hook, clear compiled views if needed: `php artisan view:clear`.

## Changing the sound or behavior

- **Tone / volume:** Edit the `sharpBeep` parameters and `peak` in `notification-sound.blade.php`.
- **Debounce / dedupe windows:** Adjust `1200` (minimum gap between chimes), `4500` (skip badge chime after Echo), or polling in the panel providers.
- **More events:** Dispatch `FilamentNotificationSound` for the target `User` whenever you want the same chime and channel semantics.
