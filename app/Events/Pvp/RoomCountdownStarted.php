<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class RoomCountdownStarted implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $countdownSeconds = 3
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'RoomCountdownStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'         => $this->roomCode,
            'countdown_seconds' => $this->countdownSeconds,
            'timestamp'         => microtime(true),
        ];
    }
}
