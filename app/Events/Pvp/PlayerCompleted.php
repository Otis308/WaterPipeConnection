<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class PlayerCompleted implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public string $playerName,
        public int $finalScore,
        public int $finishTimeMs,
        public int $currentRank
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'PlayerCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'      => $this->roomCode,
            'user_id'        => $this->userId,
            'player_name'    => $this->playerName,
            'final_score'    => $this->finalScore,
            'finish_time_ms' => $this->finishTimeMs,
            'current_rank'   => $this->currentRank,
            'timestamp'      => microtime(true),
        ];
    }
}
