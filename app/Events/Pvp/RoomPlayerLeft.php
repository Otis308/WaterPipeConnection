<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class RoomPlayerLeft implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public int $userId,
        public string $playerName,
        public int $currentPlayers,
        public int $maxPlayers,
        public ?int $newHostUserId = null
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'RoomPlayerLeft';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'        => $this->roomCode,
            'user_id'          => $this->userId,
            'player_name'      => $this->playerName,
            'current_players'  => $this->currentPlayers,
            'max_players'      => $this->maxPlayers,
            'new_host_user_id' => $this->newHostUserId,
            'timestamp'        => microtime(true),
        ];
    }
}
