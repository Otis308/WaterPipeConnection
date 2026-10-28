<?php

require_once __DIR__ . '/../stubs.php';

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bổ sung cột lưu trạng thái bàn cờ vào bảng pvp_room_players
        Schema::table('pvp_room_players', function (Blueprint $table) {
            if (!Schema::hasColumn('pvp_room_players', 'board_rotations_json')) {
                $table->mediumText('board_rotations_json')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('pvp_room_players', 'board_state_version')) {
                $table->unsignedInteger('board_state_version')->default(0)->after('board_rotations_json');
            }
        });

        // 2. Bổ sung cột win_status và k_factor vào bảng pvp_match_history
        Schema::table('pvp_match_history', function (Blueprint $table) {
            if (!Schema::hasColumn('pvp_match_history', 'win_status')) {
                $table->enum('win_status', ['win', 'loss', 'draw', 'abandoned'])->default('loss')->after('rating_delta');
            }
            if (!Schema::hasColumn('pvp_match_history', 'k_factor')) {
                $table->unsignedTinyInteger('k_factor')->default(32)->after('win_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pvp_match_history', function (Blueprint $table) {
            if (Schema::hasColumn('pvp_match_history', 'k_factor')) {
                $table->dropColumn('k_factor');
            }
            if (Schema::hasColumn('pvp_match_history', 'win_status')) {
                $table->dropColumn('win_status');
            }
        });

        Schema::table('pvp_room_players', function (Blueprint $table) {
            if (Schema::hasColumn('pvp_room_players', 'board_state_version')) {
                $table->dropColumn('board_state_version');
            }
            if (Schema::hasColumn('pvp_room_players', 'board_rotations_json')) {
                $table->dropColumn('board_rotations_json');
            }
        });
    }
};
