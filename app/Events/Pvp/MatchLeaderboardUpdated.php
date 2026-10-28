<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class MatchLeaderboardUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public array $leaderboard
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'MatchLeaderboardUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'   => $this->roomCode,
            'leaderboard' => $this->leaderboard,
            'timestamp'   => microtime(true),
        ];
    }
}
