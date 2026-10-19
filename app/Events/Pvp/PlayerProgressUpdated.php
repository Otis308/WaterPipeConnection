<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class PlayerProgressUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public string $playerName,
        public int $completedPairs,
        public int $totalPairs,
        public int $currentScore,
        public string $solveStatus = 'playing'
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'PlayerProgressUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'       => $this->roomCode,
            'user_id'         => $this->userId,
            'player_name'     => $this->playerName,
            'completed_pairs' => $this->completedPairs,
            'total_pairs'     => $this->totalPairs,
            'current_score'   => $this->currentScore,
            'solve_status'    => $this->solveStatus,
            'timestamp'       => microtime(true),
        ];
    }
}
