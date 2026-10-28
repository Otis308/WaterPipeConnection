<?php

namespace App\Events;

interface ShouldBroadcast
{
    /**
     * Tên các kênh broadcast (ví dụ: ['presence-room.HUIT88'])
     */
    public function broadcastOn(): array;

    /**
     * Tên sự kiện broadcast (ví dụ: 'RoomPlayerJoined')
     */
    public function broadcastAs(): string;

    /**
     * Dữ liệu payload gửi kèm qua WebSocket
     */
    public function broadcastWith(): array;
}
