-- =========================================================================================
-- FILE: database/migrations/2026_10_17_000001_add_board_state_to_pvp_players.sql
-- MỤC ĐÍCH: Bổ sung các cột phục vụ Reconnect (AC5) và Elo Audit (AC6) cho chế độ PvP
-- TƯƠNG THÍCH: MySQL 8.0+ (Sử dụng Stored Procedure an toàn, tránh lỗi cú pháp IF NOT EXISTS)
-- TÍNH CHẤT: IDEMPOTENT (Có thể chạy nhiều lần mà không gây lỗi hoặc mất dữ liệu hiện có)
-- =========================================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS AlterPvpTablesForBoardStateAndAudit$$

CREATE PROCEDURE AlterPvpTablesForBoardStateAndAudit()
BEGIN
    -- 1. Bổ sung cột board_rotations_json vào bảng pvp_room_players (nếu chưa có)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pvp_room_players'
          AND COLUMN_NAME = 'board_rotations_json'
    ) THEN
        ALTER TABLE pvp_room_players
        ADD COLUMN board_rotations_json MEDIUMTEXT NULL DEFAULT NULL
        COMMENT 'JSON: {r-c: rotation} — trạng thái xoay bàn cờ cá nhân, cập nhật khi thao tác';
    END IF;

    -- 2. Bổ sung cột board_state_version vào bảng pvp_room_players (nếu chưa có)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pvp_room_players'
          AND COLUMN_NAME = 'board_state_version'
    ) THEN
        ALTER TABLE pvp_room_players
        ADD COLUMN board_state_version INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Số thứ tự phiên bản trạng thái bàn cờ, tăng sau mỗi thao tác xoay';
    END IF;

    -- 3. Bổ sung cột win_status vào bảng pvp_match_history (nếu chưa có)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pvp_match_history'
          AND COLUMN_NAME = 'win_status'
    ) THEN
        ALTER TABLE pvp_match_history
        ADD COLUMN win_status ENUM('win', 'loss', 'draw', 'abandoned') NOT NULL DEFAULT 'loss'
        COMMENT 'Kết quả cá nhân: win=hạng 1, loss=hạng 2+, draw=hòa, abandoned=bỏ trận';
    END IF;

    -- 4. Bổ sung cột k_factor vào bảng pvp_match_history (nếu chưa có)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pvp_match_history'
          AND COLUMN_NAME = 'k_factor'
    ) THEN
        ALTER TABLE pvp_match_history
        ADD COLUMN k_factor TINYINT UNSIGNED NOT NULL DEFAULT 32
        COMMENT 'Hệ số K được dùng khi tính delta Elo cho trận đấu này';
    END IF;
END$$

DELIMITER ;

-- Thực thi Procedure kiểm tra và thêm cột
CALL AlterPvpTablesForBoardStateAndAudit();

-- Xóa Procedure tạm sau khi hoàn tất
DROP PROCEDURE IF EXISTS AlterPvpTablesForBoardStateAndAudit;
