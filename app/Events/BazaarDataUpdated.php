<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BazaarDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Batch of updated bazaar items (product_id → price/volume data).
     *
     * @var array<string, array>
     */
    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Public channel — all connected clients receive the update.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('bazaar');
    }

    public function broadcastAs(): string
    {
        return 'data.updated';
    }
}
