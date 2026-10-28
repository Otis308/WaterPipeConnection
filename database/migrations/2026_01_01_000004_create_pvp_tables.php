<?php

require_once __DIR__ . '/../stubs.php';

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bảng Phòng Đấu PvP (pvp_rooms)
        Schema::create('pvp_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 16)->unique();
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('room_type', ['custom', 'random'])->default('custom');
            $table->integer('max_players')->default(3); // Mặc định 3 người (hỗ trợ 2-4)
            $table->integer('current_players')->default(1);
            $table->enum('status', ['waiting', 'ready', 'starting', 'in_progress', 'completed', 'cancelled'])->default('waiting');
            $table->string('map_size', 10)->default('5x8');
            $table->string('difficulty', 10)->default('medium');
            $table->integer('num_pairs')->default(2);
            $table->string('map_seed', 64)->nullable();
            $table->text('map_config_json')->nullable();
            $table->integer('time_limit_seconds')->default(300); // 5 phút mặc định
            $table->integer('started_at_ts')->nullable();
            $table->integer('ended_at_ts')->nullable();
            $table->timestamps();
        });

        // 2. Bảng Người Chơi Trong Phòng (pvp_room_players)
        Schema::create('pvp_room_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('pvp_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('player_name', 100);
            $table->integer('seat_number')->default(1); // Ghế 1..4
            $table->boolean('is_host')->default(false);
            $table->boolean('is_ready')->default(false);
            $table->enum('connection_status', ['connected', 'disconnected'])->default('connected');
            $table->integer('current_score')->default(0);
            $table->integer('completed_pairs')->default(0);
            $table->integer('total_pairs')->default(2);
            $table->enum('solve_status', ['playing', 'completed', 'timeout', 'surrender', 'disconnected'])->default('playing');
            $table->integer('finish_time_ms')->nullable();
            $table->integer('initial_rating')->default(1000);
            $table->integer('rating_change')->default(0);
            $table->integer('final_rank')->nullable(); // #1, #2, #3, #4
            $table->integer('last_heartbeat_ts')->nullable();
            $table->timestamps();
        });

        // 3. Bảng Hàng Đợi Ghép Trận Ngẫu Nhiên (pvp_matchmaking_queue)
        Schema::create('pvp_matchmaking_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->integer('target_players')->default(3); // 2, 3, hoặc 4 người
            $table->integer('player_rating')->default(1000);
            $table->string('preferred_size', 10)->default('5x8');
            $table->string('preferred_diff', 10)->default('medium');
            $table->enum('status', ['waiting', 'matched', 'cancelled'])->default('waiting');
            $table->string('matched_room_code', 16)->nullable();
            $table->integer('queued_at_ts');
            $table->timestamps();
        });

        // 4. Bảng Lịch Sử Đấu PvP & Biến Động Rating (pvp_match_history)
        Schema::create('pvp_match_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('pvp_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('final_rank');
            $table->integer('score')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->integer('rating_before')->default(1000);
            $table->integer('rating_after')->default(1000);
            $table->integer('rating_delta')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_match_history');
        Schema::dropIfExists('pvp_matchmaking_queue');
        Schema::dropIfExists('pvp_room_players');
        Schema::dropIfExists('pvp_rooms');
    }
};
