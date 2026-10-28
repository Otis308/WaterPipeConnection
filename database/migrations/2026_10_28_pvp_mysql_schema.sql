-- Add missing PvP base tables only. Repeatable; no seed, DROP, DELETE or reset.
-- PDO runtime uses this file because the hybrid Schema facade does not execute DDL.
CREATE TABLE IF NOT EXISTS pvp_rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(16) NOT NULL UNIQUE,
    host_user_id BIGINT UNSIGNED NOT NULL,
    room_type ENUM('custom','random') NOT NULL DEFAULT 'custom',
    max_players INT NOT NULL DEFAULT 3,
    current_players INT NOT NULL DEFAULT 1,
    status ENUM('waiting','ready','starting','in_progress','completed','cancelled') NOT NULL DEFAULT 'waiting',
    map_size VARCHAR(10) NOT NULL DEFAULT '5x8',
    difficulty VARCHAR(10) NOT NULL DEFAULT 'medium',
    num_pairs INT NOT NULL DEFAULT 2,
    map_seed VARCHAR(64) NULL,
    map_config_json MEDIUMTEXT NULL,
    map_config_hash VARCHAR(64) NULL,
    map_generator_version VARCHAR(32) NULL,
    time_limit_seconds INT NOT NULL DEFAULT 300,
    started_at_ts INT NULL,
    ended_at_ts INT NULL,
    countdown_started_at INT NULL,
    event_seq INT UNSIGNED NOT NULL DEFAULT 0,
    ratings_applied TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pvp_room_players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    player_name VARCHAR(100) NOT NULL,
    seat_number INT NOT NULL DEFAULT 1,
    is_host TINYINT(1) NOT NULL DEFAULT 0,
    is_ready TINYINT(1) NOT NULL DEFAULT 0,
    connection_status ENUM('connected','disconnected') NOT NULL DEFAULT 'connected',
    current_score INT NOT NULL DEFAULT 0,
    completed_pairs INT NOT NULL DEFAULT 0,
    total_pairs INT NOT NULL DEFAULT 2,
    solve_status ENUM('playing','completed','timeout','surrender','disconnected') NOT NULL DEFAULT 'playing',
    finish_time_ms INT NULL,
    initial_rating INT NOT NULL DEFAULT 1000,
    rating_change INT NOT NULL DEFAULT 0,
    final_rank INT NULL,
    last_heartbeat_ts INT NULL,
    board_rotations_json MEDIUMTEXT NULL,
    board_state_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY pvp_room_players_room_user_uq (room_id,user_id),
    FOREIGN KEY (room_id) REFERENCES pvp_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pvp_matchmaking_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    target_players INT NOT NULL DEFAULT 3,
    player_rating INT NOT NULL DEFAULT 1000,
    preferred_size VARCHAR(10) NOT NULL DEFAULT '5x8',
    preferred_diff VARCHAR(10) NOT NULL DEFAULT 'medium',
    status ENUM('waiting','matched','cancelled') NOT NULL DEFAULT 'waiting',
    matched_room_code VARCHAR(16) NULL,
    queued_at_ts INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY pvp_queue_waiting (target_players,status,queued_at_ts),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pvp_match_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    final_rank INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    duration_ms INT NOT NULL DEFAULT 0,
    rating_before INT NOT NULL DEFAULT 1000,
    rating_after INT NOT NULL DEFAULT 1000,
    rating_delta INT NOT NULL DEFAULT 0,
    win_status ENUM('win','loss','draw','abandoned') NOT NULL DEFAULT 'loss',
    k_factor TINYINT UNSIGNED NOT NULL DEFAULT 32,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY pvp_match_history_room_user_uq (room_id,user_id),
    FOREIGN KEY (room_id) REFERENCES pvp_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
