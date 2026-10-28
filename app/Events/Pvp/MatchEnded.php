<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class MatchEnded implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public string $reason = 'all_completed', // 'all_completed', 'time_out', 'host_cancelled'
        public array $finalLeaderboard = []
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'MatchEnded';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'         => $this->roomCode,
            'reason'            => $this->reason,
            'final_leaderboard' => $this->finalLeaderboard,
            'timestamp'         => microtime(true),
        ];
    }
}
