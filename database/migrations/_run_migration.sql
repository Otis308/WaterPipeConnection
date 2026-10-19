SET @dbname = DATABASE();

-- 1. board_rotations_json
SET @precheck1 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pvp_room_players' AND COLUMN_NAME = 'board_rotations_json');
SET @sql1 = IF(@precheck1 = 0, 'ALTER TABLE pvp_room_players ADD COLUMN board_rotations_json MEDIUMTEXT NULL DEFAULT NULL COMMENT \'JSON: {r-c: rotation}\'', 'SELECT \"board_rotations_json already exists\" AS info');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- 2. board_state_version
SET @precheck2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pvp_room_players' AND COLUMN_NAME = 'board_state_version');
SET @sql2 = IF(@precheck2 = 0, 'ALTER TABLE pvp_room_players ADD COLUMN board_state_version INT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'Phiên bản trạng thái bàn cờ\'', 'SELECT \"board_state_version already exists\" AS info');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 3. win_status
SET @precheck3 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pvp_match_history' AND COLUMN_NAME = 'win_status');
SET @sql3 = IF(@precheck3 = 0, 'ALTER TABLE pvp_match_history ADD COLUMN win_status ENUM(\'win\',\'loss\',\'draw\',\'abandoned\') NOT NULL DEFAULT \'loss\' COMMENT \'Kết quả cá nhân\'', 'SELECT \"win_status already exists\" AS info');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 4. k_factor
SET @precheck4 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pvp_match_history' AND COLUMN_NAME = 'k_factor');
SET @sql4 = IF(@precheck4 = 0, 'ALTER TABLE pvp_match_history ADD COLUMN k_factor TINYINT UNSIGNED NOT NULL DEFAULT 32 COMMENT \'K-factor dùng trong trận\'', 'SELECT \"k_factor already exists\" AS info');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

SELECT 'Migration validation finished' AS status;
