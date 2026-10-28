SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `huit_water_pipe_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `huit_water_pipe_db`;

-- 1. BẢNG USERS (Xác thực & Phân quyền RBAC: admin, staff, user)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu băm Bcrypt (password_hash / Hash::make)',
  `role` ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user' COMMENT 'Phân quyền: Admin, Staff, User',
  `high_score` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_games` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_wins` INT UNSIGNED NOT NULL DEFAULT 0,
  `coins` INT UNSIGNED NOT NULL DEFAULT 500,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG USER_PROFILES (Quan hệ 1-1: User hasOne UserProfile / UserProfile belongsTo User)
DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `birthday` DATE NULL,
  `avatar` VARCHAR(255) NULL DEFAULT 'Images/CupSao.png',
  `preferred_mode` VARCHAR(20) NOT NULL DEFAULT 'player',
  `sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `music_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG PIPE_CATEGORIES (Quan hệ 1-N: Một chủ đề có nhiều Màn chơi & Vật phẩm)
DROP TABLE IF EXISTS `pipe_categories`;
CREATE TABLE `pipe_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `score_multiplier` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG GAME_LEVELS (Quan hệ 1-N với pipe_categories & users)
DROP TABLE IF EXISTS `game_levels`;
CREATE TABLE `game_levels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(170) NOT NULL UNIQUE,
  `map_size` VARCHAR(10) NOT NULL DEFAULT '3x8',
  `grid_rows` INT UNSIGNED NOT NULL DEFAULT 3,
  `grid_cols` INT UNSIGNED NOT NULL DEFAULT 8,
  `difficulty` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'easy',
  `time_limit_seconds` INT UNSIGNED NOT NULL DEFAULT 60,
  `reward_stars` INT UNSIGNED NOT NULL DEFAULT 100,
  `thumbnail` VARCHAR(255) NULL DEFAULT 'Images/NenNho.jpg',
  `description` TEXT NULL,
  `map_config_json` JSON NULL COMMENT 'Cấu hình NoSQL JSON của bản đồ và các loại ống',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_levels_category` FOREIGN KEY (`category_id`) REFERENCES `pipe_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_levels_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG TAGS & BẢNG TRUNG GIAN GAME_LEVEL_TAG (Quan hệ N-N: belongsToMany)
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(80) NOT NULL UNIQUE,
  `color_hex` VARCHAR(15) NOT NULL DEFAULT '#01caee',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `game_level_tag`;
CREATE TABLE `game_level_tag` (
  `game_level_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`game_level_id`, `tag_id`),
  CONSTRAINT `fk_piv_level` FOREIGN KEY (`game_level_id`) REFERENCES `game_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_piv_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG GAME_SESSIONS (Lịch sử đấu & Telemetry AI Leo Đồi NoSQL JSON)
DROP TABLE IF EXISTS `game_sessions`;
CREATE TABLE `game_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `game_level_id` BIGINT UNSIGNED NULL,
  `player_name` VARCHAR(60) NOT NULL DEFAULT 'Guest',
  `game_mode` ENUM('player', 'ai') NOT NULL DEFAULT 'player',
  `map_option` ENUM('random', 'custom') NOT NULL DEFAULT 'random',
  `map_size` VARCHAR(10) NOT NULL DEFAULT '3x8',
  `difficulty` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'easy',
  `result` ENUM('win', 'lose', 'surrender') NOT NULL DEFAULT 'win',
  `score_earned` INT UNSIGNED NOT NULL DEFAULT 0,
  `connected_pipes` INT UNSIGNED NOT NULL DEFAULT 0,
  `steps_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `ai_retries` INT UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `telemetry_nosql` JSON NULL COMMENT 'Dữ liệu NoSQL lưu vết thuật toán AI Hill-Climbing và trạng thái ống',
  `played_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sessions_level` FOREIGN KEY (`game_level_id`) REFERENCES `game_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG ACHIEVEMENTS & ACHIEVEMENT_USER (Quan hệ N-N giữa Users và Thành tựu)
DROP TABLE IF EXISTS `achievements`;
CREATE TABLE `achievements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `min_score` INT UNSIGNED NOT NULL DEFAULT 100,
  `badge_icon` VARCHAR(255) NOT NULL DEFAULT 'Images/CupSao.png',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `achievement_user`;
CREATE TABLE `achievement_user` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `achievement_id` BIGINT UNSIGNED NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `achievement_id`),
  CONSTRAINT `fk_ach_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ach_item` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG SHOP_ITEMS, SHOP_ORDERS, SHOP_ORDER_DETAILS (Phục vụ bài toán Cửa hàng & Lab 5 SQL nâng cao)
DROP TABLE IF EXISTS `shop_items`;
CREATE TABLE `shop_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL UNIQUE,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 50000.00,
  `stock` INT UNSIGNED NOT NULL DEFAULT 100,
  `pipe_skin_folder` VARCHAR(100) NOT NULL DEFAULT 'OngXanh',
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_shop_category` FOREIGN KEY (`category_id`) REFERENCES `pipe_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_orders`;
CREATE TABLE `shop_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_date` DATE NOT NULL,
  `payment_gateway` ENUM('momo', 'bank') NOT NULL DEFAULT 'momo',
  `status` ENUM('completed', 'pending', 'cancelled') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_order_details`;
CREATE TABLE `shop_order_details` (
  `order_id` BIGINT UNSIGNED NOT NULL,
  `shop_item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_id`, `shop_item_id`),
  CONSTRAINT `fk_od_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_od_item` FOREIGN KEY (`shop_item_id`) REFERENCES `shop_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG PASSWORD_RESET_OTPS (Khôi phục mật khẩu bằng OTP 6 chữ số - Hiệu lực 60 giây)
DROP TABLE IF EXISTS `password_reset_otps`;
CREATE TABLE `password_reset_otps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Khóa ngoại liên kết tới users.id',
  `request_token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Mã định danh yêu cầu khôi phục duy nhất',
  `otp_hash` VARCHAR(255) NOT NULL COMMENT 'Giá trị băm Bcrypt của mã OTP 6 chữ số (không lưu plain text)',
  `reset_token_hash` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Giá trị băm SHA-256 của token đặt lại mật khẩu sau khi verify OTP',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số lần nhập OTP sai hiện tại',
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Giới hạn số lần nhập OTP sai tối đa',
  `expires_at_ts` BIGINT UNSIGNED NOT NULL COMMENT 'Unix Timestamp hết hạn OTP (chính xác 60 giây sau khi phát hành)',
  `verified_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Unix Timestamp thời điểm xác minh OTP thành công',
  `reset_expires_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Unix Timestamp hết hạn quyền đặt lại mật khẩu',
  `invalidated_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Unix Timestamp thời điểm OTP đã dùng hoặc bị vô hiệu hóa khi gửi lại mã mới',
  `created_at_ts` BIGINT UNSIGNED NOT NULL COMMENT 'Unix Timestamp thời điểm tạo yêu cầu để kiểm tra cooldown 60s',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_otp_user_created` (`user_id`, `created_at_ts`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BẢNG USER_SESSIONS (Quản lý phiên đăng nhập & Token xác thực an toàn theo user_id)
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `session_token_hash` VARCHAR(128) NOT NULL UNIQUE COMMENT 'Băm SHA-256 của token phiên đăng nhập',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `expires_at_ts` BIGINT UNSIGNED NOT NULL,
  `revoked_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at_ts` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sessions_user` (`user_id`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. BẢNG FRIENDSHIPS (Kết bạn & Tìm kiếm đối thủ PvP liên kết qua user_id duy nhất)
DROP TABLE IF EXISTS `friendships`;
CREATE TABLE `friendships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `friend_user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'accepted', 'blocked') NOT NULL DEFAULT 'accepted',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friendship_pair` (`user_id`, `friend_user_id`),
  CONSTRAINT `fk_friend_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_target` FOREIGN KEY (`friend_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_otps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `request_token` VARCHAR(64) NOT NULL UNIQUE,
  `otp_hash` VARCHAR(255) NOT NULL,
  `reset_token_hash` VARCHAR(255) NULL DEFAULT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
  `expires_at_ts` BIGINT UNSIGNED NOT NULL,
  `verified_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reset_expires_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invalidated_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at_ts` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_otp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng lưu trữ phiên đăng nhập và token xác thực an toàn
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `session_token_hash` VARCHAR(128) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `expires_at_ts` BIGINT UNSIGNED NOT NULL,
  `revoked_at_ts` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at_ts` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sessions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================================
-- DỮ LIỆU MẪU (SEEDER)
-- Mật khẩu mặc định cho tất cả tài khoản bên dưới là: password123
-- Hash Bcrypt của 'password123': $2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2
-- =========================================================================================

INSERT INTO `users` (`id`, `username`, `name`, `email`, `password`, `role`, `high_score`, `total_games`, `total_wins`, `coins`, `is_active`) VALUES
(1, 'admin', 'TS. Nguyễn Quản Trị (Admin)', 'admin@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'admin', 1250, 25, 22, 9999, 1),
(2, 'staff', 'ThS. Trần Thiết Kế Màn Chơi (Staff)', 'staff@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'staff', 980, 20, 16, 3500, 1),
(3, 'player1', 'Lê Hoàng An (Sinh viên HUIT)', 'player1@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'user', 1120, 30, 24, 1800, 1),
(4, 'player2', 'Phạm Minh Tuấn', 'tuan.pm@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'user', 860, 18, 12, 1200, 1),
(5, 'player3', 'Đỗ Ngọc Bích', 'bich.dn@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'user', 640, 15, 9, 950, 1),
(6, 'staff2', 'Võ Quốc Huy (Staff AI)', 'huy.vq@huit.edu.vn', '$2y$10$X5nyUEWvfD23BBPdm2VnOOLc6nV2IYG/YhpwkrdKGz76XPHXOeFQ2', 'staff', 790, 12, 10, 2100, 1);

INSERT INTO `user_profiles` (`user_id`, `birthday`, `avatar`, `preferred_mode`, `sound_enabled`, `music_enabled`) VALUES
(1, '1990-05-15', 'Images/CupSao.png', 'player', 1, 1),
(2, '1995-08-20', 'Images/CupSao.png', 'ai', 1, 1),
(3, '2004-11-02', 'Images/CupSao.png', 'player', 1, 1),
(4, '2004-03-19', 'Images/CupSao.png', 'ai', 1, 0),
(5, '2005-07-10', 'Images/CupSao.png', 'player', 1, 1),
(6, '1998-12-01', 'Images/CupSao.png', 'ai', 1, 1);

INSERT INTO `pipe_categories` (`id`, `name`, `slug`, `description`, `score_multiplier`) VALUES
(1, 'Hệ thống Ống Xanh Cơ Bản', 'he-thong-ong-xanh-co-ban', 'Các màn chơi tiêu chuẩn với ống xanh có thể xoay 360 độ, phù hợp rèn luyện tư duy.', 1.00),
(2, 'Mê Cung Ống Đỏ Cố Định', 'me-cung-ong-do-co-dinh', 'Bổ sung các đoạn ống đỏ khóa cứng và tường chắn đòi hỏi định tuyến vòng.', 1.50),
(3, 'Thử Thách Ống Vàng Hoán Đổi', 'thu-thach-ong-vang-hoan-doi', 'Đỉnh cao chiến thuật với cụm ống vàng chữ L cho phép tráo đổi vị trí liền kề.', 2.00),
(4, 'Phòng Thí Nghiệm AI Leo Đồi', 'phong-thi-nghiem-ai-leo-doi', 'Các bản đồ chuyên biệt để kiểm thử thuật toán AI Hill-Climbing và Random Restart.', 1.75);

INSERT INTO `game_levels` (`id`, `category_id`, `created_by`, `title`, `slug`, `map_size`, `grid_rows`, `grid_cols`, `difficulty`, `time_limit_seconds`, `reward_stars`, `thumbnail`, `description`, `map_config_json`, `is_published`) VALUES
(1, 1, 1, 'Khởi nguồn Dòng chảy Tân Phú', 'khoi-nguon-dong-chay-tan-phu', '3x8', 3, 8, 'easy', 60, 100, 'Images/NenNho.jpg', 'Màn chơi mở đầu 3x8 giúp làm quen với thao tác xoay ống thẳng và ống chữ L.', '{"yellowProb":0,"redProb":0,"wallProb":0,"crossProb":0.1}', 1),
(2, 1, 2, 'Trạm Bơm Nước Trung Tâm', 'tram-bom-nuoc-trung-tam', '3x10', 3, 10, 'easy', 60, 120, 'Images/NenNho.jpg', 'Mở rộng lưới lên 3x10 với nhiều nhánh rẽ chữ thập thú vị.', '{"yellowProb":0,"redProb":0,"wallProb":0,"crossProb":0.2}', 1),
(3, 1, 2, 'Đường Ống Cao Áp Đông Sài Gòn', 'duong-ong-cao-ap-dong-sai-gon', '3x12', 3, 12, 'medium', 55, 180, 'Images/NenNho.jpg', 'Đường ống trải dài 12 cột kèm theo các đoạn ống đỏ cố định.', '{"yellowProb":0,"redProb":0.15,"wallProb":0.1,"crossProb":0.15}', 1),
(4, 2, 1, 'Vượt Tường Đá Ngầm', 'vuot-tuong-da-ngam', '5x6', 5, 6, 'medium', 50, 200, 'Images/NenCaroDo.jpg', 'Bản đồ 5x6 xuất hiện chướng ngại vật tường gạch buộc dòng nước phải uốn lượn.', '{"yellowProb":0,"redProb":0.2,"wallProb":0.18,"crossProb":0.1}', 1),
(5, 2, 2, 'Mạng Lưới Thủy Cục Cấp Độ 2', 'mang-luoi-thuy-cuc-cap-do-2', '5x8', 5, 8, 'medium', 50, 220, 'Images/NenCaroDo.jpg', 'Kết hợp ống chữ thập phân nhánh và các điểm khóa đỏ phức tạp.', '{"yellowProb":0,"redProb":0.2,"wallProb":0.15,"crossProb":0.2}', 1),
(6, 3, 2, 'Giải Mã Cụm Ống Vàng Liền Kề', 'giai-ma-cum-ong-vang-lien-ke', '5x10', 5, 10, 'hard', 60, 300, 'Images/NenLon.png', 'Người chơi phải vừa xoay ống xanh vừa hoán đổi vị trí các cặp ống vàng chữ L.', '{"yellowProb":0.25,"redProb":0.15,"wallProb":0.15,"crossProb":0.1}', 1),
(7, 3, 1, 'Siêu Đô Thị Thủy Lợi 5x12', 'sieu-do-thi-thuy-loi-5x12', '5x12', 5, 12, 'hard', 60, 350, 'Images/NenLon.png', 'Bản đồ kích thước tối đa 5x12 (60 ô) thử thách cực đại cho cả Player và AI.', '{"yellowProb":0.3,"redProb":0.18,"wallProb":0.15,"crossProb":0.15}', 1),
(8, 4, 6, 'Benchmark AI Hill-Climbing #01', 'benchmark-ai-hill-climbing-01', '5x8', 5, 8, 'hard', 60, 280, 'Images/NenLon.png', 'Bản đồ chuẩn hóa để đo thời gian hội tụ và số lần Random Restart của AI.', '{"yellowProb":0.25,"redProb":0.15,"wallProb":0.15,"crossProb":0.12}', 1);

INSERT INTO `tags` (`id`, `name`, `slug`, `color_hex`) VALUES
(1, 'Tân thủ (Beginner)', 'tan-thu', '#22c55e'),
(2, 'Ống Vàng (Swap Pipe)', 'ong-vang', '#eab308'),
(3, 'Ống Đỏ (Fixed Pipe)', 'ong-do', '#ef4444'),
(4, 'Tối ưu AI (Heuristic)', 'toi-uu-ai', '#06b6d4'),
(5, 'Bản đồ Lớn (5x12)', 'ban-do-lon', '#a855f7');

INSERT INTO `game_level_tag` (`game_level_id`, `tag_id`) VALUES
(1, 1),
(2, 1),
(3, 3),
(4, 3),
(5, 3), (5, 4),
(6, 2), (6, 3),
(7, 2), (7, 3), (7, 5),
(8, 2), (8, 4), (8, 5);

INSERT INTO `game_sessions` (`user_id`, `game_level_id`, `player_name`, `game_mode`, `map_option`, `map_size`, `difficulty`, `result`, `score_earned`, `connected_pipes`, `steps_count`, `ai_retries`, `duration_ms`, `telemetry_nosql`, `played_at`) VALUES
(1, 7, 'admin', 'player', 'custom', '5x12', 'hard', 'win', 420, 14, 28, 0, 34200, '{"algorithm":"player_manual","swaps":4,"rotations":24}', '2026-10-10 09:15:00'),
(3, 6, 'player1', 'player', 'custom', '5x10', 'hard', 'win', 380, 12, 22, 0, 29500, '{"algorithm":"player_manual","swaps":3,"rotations":19}', '2026-10-11 14:20:00'),
(2, 8, 'staff', 'ai', 'custom', '5x8', 'hard', 'win', 310, 11, 45, 2, 18450, '{"algorithm":"steepest_hill_climbing","heuristic_peak":11,"restarts":2}', '2026-10-12 10:05:00'),
(4, 5, 'player2', 'player', 'random', '5x8', 'medium', 'win', 260, 9, 17, 0, 24100, '{"algorithm":"player_manual","swaps":0,"rotations":17}', '2026-10-12 16:40:00'),
(5, 1, 'player3', 'player', 'custom', '3x8', 'easy', 'win', 160, 6, 10, 0, 15200, '{"algorithm":"player_manual","swaps":0,"rotations":10}', '2026-10-13 20:10:00'),
(3, 7, 'player1', 'player', 'custom', '5x12', 'hard', 'lose', 90, 9, 31, 0, 60000, '{"algorithm":"player_manual","reason":"timeout"}', '2026-10-14 08:30:00'),
(6, 7, 'staff2', 'ai', 'custom', '5x12', 'hard', 'win', 390, 13, 52, 3, 22890, '{"algorithm":"steepest_hill_climbing","heuristic_peak":13,"restarts":3}', '2026-10-14 19:45:00');

INSERT INTO `achievements` (`id`, `code`, `title`, `description`, `min_score`, `badge_icon`) VALUES
(1, 'FIRST_FLOW', 'Kỹ Sư Đường Ống Tập Sự', 'Hoàn thành màn chơi nối ống nước đầu tiên.', 100, 'Images/Sao.svg'),
(2, 'GOLDEN_SWAPPER', 'Bậc Thầy Hoán Đổi Ống Vàng', 'Chiến thắng màn chơi chế độ Hard có cụm ống vàng.', 300, 'Images/CupSao.png'),
(3, 'HYDRO_GRANDMASTER', 'Tổng Công Trình Sư Thủy Lợi', 'Đạt kỷ lục điểm số từ 1.000 sao trở lên.', 1000, 'Images/CupSao.png'),
(4, 'AI_EXPLORER', 'Nhà Nghiên Cứu Trí Tuệ Nhân Tạo', 'Chạy thử nghiệm thành công thuật toán AI Leo Đồi.', 200, 'Images/CupSao.png');

INSERT INTO `achievement_user` (`user_id`, `achievement_id`, `unlocked_at`) VALUES
(1, 1, '2026-10-01 10:00:00'),
(1, 2, '2026-10-05 11:20:00'),
(1, 3, '2026-10-10 09:16:00'),
(2, 1, '2026-10-02 15:00:00'),
(2, 4, '2026-10-12 10:06:00'),
(3, 1, '2026-10-03 18:00:00'),
(3, 2, '2026-10-11 14:21:00'),
(3, 3, '2026-10-11 14:21:00');

INSERT INTO `shop_items` (`id`, `category_id`, `name`, `slug`, `price`, `stock`, `pipe_skin_folder`, `description`) VALUES
(1, 1, 'Bộ Ống Xanh Ngọc Bích (Jade Pipe)', 'bo-ong-xanh-ngoc-bich', 150000.00, 50, 'OngXanh', 'Bộ skin ống nước xanh ngọc tăng hiệu ứng dòng chảy mượt mà.'),
(2, 1, 'Đồng Hồ Nước Tăng Áp (+15s)', 'dong-ho-nuoc-tang-ap', 85000.00, 120, 'OngXanh', 'Tăng thêm 15 giây cho cột nước thời gian trong chế độ Player.'),
(3, 2, 'Bộ Ống Đỏ Titan Chịu Nhiệt', 'bo-ong-do-titan-chiu-nhiet', 250000.00, 35, 'OngDo', 'Skin ống đỏ phong cách công nghiệp nặng.'),
(4, 2, 'La Bàn Định Tuyến Nguồn Nước', 'la-ban-dinh-tuyen-nguon-nuoc', 190000.00, 60, 'OngDo', 'Hiển thị gợi ý hướng mở của ống bắt đầu và kết thúc.'),
(5, 3, 'Bộ Ống Vàng Hoàng Gia 24K', 'bo-ong-vang-hoang-gia-24k', 450000.00, 20, 'OngVang', 'Skin ống vàng chữ L mạ vàng lấp lánh khi tráo đổi vị trí.'),
(6, 3, 'Gói Cứu Trợ AI Giải Hộ Đặc Biệt', 'goi-cuu-tro-ai-giai-ho', 320000.00, 40, 'OngVang', 'Cho phép kích hoạt AI giải hộ mà vẫn bảo toàn điểm số hiện tại.'),
(7, 4, 'Mô-đun Phân Tích Heuristic Pro', 'mo-dun-phan-tich-heuristic-pro', 500000.00, 15, 'OngXanh', 'Hiển thị đồ thị trực quan từng bước leo đồi của AI.'),
(8, 4, 'Bộ Ống Kim Cương Vũ Trụ (Chưa ai mua)', 'bo-ong-kim-cuong-vu-tru', 990000.00, 10, 'OngVang', 'Vật phẩm hiếm dùng để kiểm thử truy vấn LEFT JOIN ... IS NULL (chưa từng được đặt hàng).');

INSERT INTO `shop_orders` (`id`, `user_id`, `order_date`, `payment_gateway`, `status`) VALUES
(1, 3, '2026-10-10', 'momo', 'completed'),
(2, 1, '2026-10-11', 'bank', 'completed'),
(3, 4, '2026-10-11', 'momo', 'completed'),
(4, 2, '2026-10-12', 'bank', 'completed'),
(5, 3, '2026-10-13', 'momo', 'completed');

INSERT INTO `shop_order_details` (`order_id`, `shop_item_id`, `quantity`, `price`) VALUES
(1, 1, 2, 150000.00),
(1, 2, 3, 85000.00),
(2, 5, 2, 450000.00),
(2, 7, 1, 500000.00),
(3, 2, 2, 85000.00),
(3, 4, 1, 190000.00),
(4, 6, 2, 320000.00),
(5, 5, 1, 450000.00);

SET FOREIGN_KEY_CHECKS = 1;
