<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Model GameSession
 *
 * @method static \App\Models\GameSession create(array $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \App\Models\GameSession|null find(mixed $id)
 */
class GameSession extends Model
{
    use HasFactory;

    protected $table = 'game_sessions';

    protected $fillable = [
        'user_id',
        'game_level_id',
        'player_name',
        'game_mode',
        'map_option',
        'map_size',
        'difficulty',
        'result',
        'score_earned',      // Điểm số đạt được (score)
        'connected_pipes',
        'steps_count',
        'ai_retries',
        'duration_ms',       // Thời gian hoàn thành tính bằng mili-giây (completion_time)
        'telemetry_nosql',
        'played_at',
    ];

    protected $casts = [
        'telemetry_nosql' => 'array',
        'score_earned'    => 'integer',
        'duration_ms'     => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Truy vấn Eloquent ORM lấy Top 10 người chơi hoàn thành nhanh nhất:
     * - Chỉ lọc các ván thắng (result = 'win') và có thời gian hợp lệ (duration_ms > 0)
     * - Gom nhóm theo user_id để mỗi người chơi xuất hiện 1 lần với kỷ lục tốt nhất của họ
     * - Sắp xếp theo thời gian hoàn thành nhanh nhất (ASC) và điểm số cao nhất (DESC)
     */
    public function scopeTop10FastestPlayers(Builder $query, ?string $mapSize = null, ?string $difficulty = null): Builder
    {
        $query->with('user:id,username,name,high_score,total_games,total_wins')
              ->select([
                  'user_id',
                  'player_name',
                  DB::raw('MAX(score_earned) AS best_score'),
                  DB::raw('MIN(duration_ms) AS fastest_completion_ms'),
                  DB::raw('ROUND(MIN(duration_ms) / 1000.0, 2) AS completion_time_seconds'),
                  DB::raw('COUNT(id) AS total_wins_recorded'),
              ])
              ->where('result', 'win')
              ->where('duration_ms', '>', 0)
              ->whereNotNull('user_id');

        if (!empty($mapSize)) {
            $query->where('map_size', $mapSize);
        }
        if (!empty($difficulty)) {
            $query->where('difficulty', $difficulty);
        }

        return $query->groupBy('user_id', 'player_name')
                     ->orderBy('fastest_completion_ms', 'asc') // Ưu tiên hoàn thành nhanh nhất
                     ->orderByDesc('best_score')               // Nếu bằng thời gian thì xét điểm cao hơn
                     ->limit(10);
    }
}