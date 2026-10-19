<?php

namespace App\Events\Pvp;

use App\Events\ShouldBroadcast;

class PlayerRankUpdated implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
        public int $ratingBefore,
        public int $ratingAfter,
        public int $ratingDelta,
        public string $tierName,
        public string $tierColor,
        public string $tierBadge
    ) {}

    public function broadcastOn(): array
    {
        return ['private-user.' . $this->userId];
    }

    public function broadcastAs(): string
    {
        return 'PlayerRankUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'       => $this->userId,
            'rating_before' => $this->ratingBefore,
            'rating_after'  => $this->ratingAfter,
            'rating_delta'  => $this->ratingDelta,
            'tier_name'     => $this->tierName,
            'tier_color'    => $this->tierColor,
            'tier_badge'    => $this->tierBadge,
            'timestamp'     => microtime(true),
        ];
    }
}
