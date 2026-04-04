<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires immediately (no queue) so the panel can play a sound in real time.
 * Filament's own broadcast notification is queued, so it often never reaches Echo without queue:work.
 */
class FilamentNotificationSound implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if (method_exists($this->user, 'receivesBroadcastNotificationsOn')) {
            return [new PrivateChannel($this->user->receivesBroadcastNotificationsOn())];
        }

        $userClass = str_replace('\\', '.', $this->user::class);

        return [new PrivateChannel("{$userClass}.{$this->user->getKey()}")];
    }

    public function broadcastAs(): string
    {
        return 'filament-notification.sound';
    }
}
