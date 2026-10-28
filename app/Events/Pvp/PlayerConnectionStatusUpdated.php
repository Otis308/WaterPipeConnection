<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class PlayerConnectionStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public string $status // 'connected' or 'disconnected'
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'PlayerConnectionStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->roomCode,
            'user_id'   => $this->userId,
            'status'    => $this->status,
            'timestamp' => microtime(true),
        ];
    }
}
