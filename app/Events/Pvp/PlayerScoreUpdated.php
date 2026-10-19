<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class PlayerScoreUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public int $currentScore,
        public int $completedPairs
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'PlayerScoreUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'       => $this->roomCode,
            'user_id'         => $this->userId,
            'current_score'   => $this->currentScore,
            'completed_pairs' => $this->completedPairs,
            'timestamp'       => microtime(true),
        ];
    }
}
