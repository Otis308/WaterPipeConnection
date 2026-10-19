<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class MatchStarted implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public array $mapData,
        public array $players,
        public int $timeLimitSeconds = 300,
        public int $startedAtTs = 0
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'MatchStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'          => $this->roomCode,
            'map_data'           => $this->mapData,
            'players'            => $this->players,
            'time_limit_seconds' => $this->timeLimitSeconds,
            'started_at_ts'      => $this->startedAtTs,
            'timestamp'          => microtime(true),
        ];
    }
}
