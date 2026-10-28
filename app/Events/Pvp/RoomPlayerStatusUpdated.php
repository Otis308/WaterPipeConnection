<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class RoomPlayerStatusUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public bool $isReady,
        public string $roomStatus
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'RoomPlayerStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'   => $this->roomCode,
            'user_id'     => $this->userId,
            'is_ready'    => $this->isReady,
            'room_status' => $this->roomStatus,
            'timestamp'   => microtime(true),
        ];
    }
}
