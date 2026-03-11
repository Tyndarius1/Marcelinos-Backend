<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for broadcast events.
 * Ensures consistent structure and makes it easy to add new events.
 */
abstract class BaseBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The event name as received by the client (Echo .listen('.EventName')).
     */
    public function broadcastAs(): string
    {
        return class_basename(static::class);
    }

    /**
     * Data sent to the client. Override in subclasses.
     *
     * @return array<string, mixed>
     */
    abstract public function broadcastWith(): array;
}
