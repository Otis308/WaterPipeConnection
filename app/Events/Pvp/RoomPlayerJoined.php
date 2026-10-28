<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class RoomPlayerJoined implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public array $player,
        public int $currentPlayers,
        public int $maxPlayers,
        public string $status
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'RoomPlayerJoined';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'        => $this->roomCode,
            'player'           => $this->player,
            'current_players'  => $this->currentPlayers,
            'max_players'      => $this->maxPlayers,
            'status'           => $this->status,
            'timestamp'        => microtime(true),
        ];
    }
}
