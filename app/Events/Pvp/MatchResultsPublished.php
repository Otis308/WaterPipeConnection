<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class MatchResultsPublished implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public array $results
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'MatchResultsPublished';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->roomCode,
            'results'   => $this->results,
            'timestamp' => microtime(true),
        ];
    }
}
