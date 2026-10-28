<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class RoomConfigurationUpdated implements ShouldBroadcast
{
    public function __construct(
        public string $roomCode,
        public string $mapSize,
        public string $difficulty,
        public int $numPairs,
        public int $maxPlayers
    ) {}

    public function broadcastOn(): array
    {
        return ['presence-room.' . $this->roomCode];
    }

    public function broadcastAs(): string
    {
        return 'RoomConfigurationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'room_code'    => $this->roomCode,
            'map_size'     => $this->mapSize,
            'difficulty'   => $this->difficulty,
            'num_pairs'    => $this->numPairs,
            'max_players'  => $this->maxPlayers,
            'timestamp'    => microtime(true),
        ];
    }
}
