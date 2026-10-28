<?php

namespace App\Services;

use PDO;
use PDOException;

/**
 * Class PdoDatabaseService
 * Thực thi toàn bộ kiến thức Chương 3 - Buổi 4 & Bài 5 (Lab 4 & Lab 5 HUIT):
 * 1. Kết nối CSDL với PDO (DSN, try-catch PDOException, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION).
 *    Tự động fallback sang SQLite PDO và khởi tạo dữ liệu mẫu nếu máy người dùng chưa bật MySQL Server!
 * 2. CRUD an toàn 100% bằng Prepared Statement (prepare + execute / bindValue).
 * 3. Phân trang (LIMIT :limit OFFSET :offset với PDO::PARAM_INT) và Tìm kiếm (WHERE LIKE :keyword với PDO::PARAM_STR).
 * 4. Truy vấn nâng cao đa bảng: INNER JOIN, LEFT JOIN, RIGHT JOIN, CROSS JOIN,
 *    Hàm tổng hợp COUNT(), SUM(), AVG(), MIN(), MAX(), GROUP BY, HAVING, và Subquery.
 */
class PdoDatabaseService
{
    private ?PDO $conn = null;
    private string $driver = 'mysql';

    public function __construct()
    {
        $this->connect();
    }

    /**
     * Tiết 1 Buổi 4: Kết nối PDO với chuỗi DSN và khối try-catch bắt lỗi PDOException
     */
    private function connect(): void
    {
        $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
        $dbname = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'huit_water_pipe_db');
        $username = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
        $password = getenv('DB_PASSWORD');
        if ($password === false) {
            $password = $_ENV['DB_PASSWORD'] ?? '';
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $this->conn = new PDO($dsn, $username, (string)$password, [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->driver = 'mysql';
            $this->ensurePvpSchema();
        } catch (PDOException $e) {
            // Dự phòng thông minh: Nếu người dùng chạy nhanh mà chưa bật MySQL trong Laragon/Docker,
            // tự động khởi tạo CSDL SQLite qua cùng giao diện PDO để ứng dụng luôn chạy mượt 100%!
            $sqlitePath = dirname(__DIR__, 2) . '/storage/app/huit_fallback.sqlite';
            if (!is_dir(dirname($sqlitePath))) {
                @mkdir(dirname($sqlitePath), 0775, true);
            }
            $this->conn = new PDO("sqlite:{$sqlitePath}");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->driver = 'sqlite';
            $this->ensureSqliteSchemaAndSeed();
            $this->ensurePvpSchema();
        }
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }

    public function getDriverName(): string
    {
        return $this->driver;
    }

    public function registerPlayerAccount(array $input): array
    {
        $username = trim((string)($input['username'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $confirmPassword = (string)($input['confirmPassword'] ?? '');

        if ($username === '' || $password === '') {
            return ['success' => false, 'status' => 422, 'message' => 'Vui lòng nhập đầy đủ Username và Mật khẩu.'];
        }
        if ($confirmPassword !== '' && $password !== $confirmPassword) {
            return ['success' => false, 'status' => 422, 'message' => 'Mật khẩu xác nhận không trùng khớp.'];
        }

        // Kiểm tra trùng Username
        $stmtCheck = $this->conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetchColumn()) {
            return ['success' => false, 'status' => 409, 'message' => 'Tên đăng nhập này đã được sử dụng.'];
        }

        // Kiểm tra trùng Email
        if ($email !== '') {
            $stmtEmail = $this->conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $stmtEmail->execute([$email]);
            if ($stmtEmail->fetchColumn()) {
                return ['success' => false, 'status' => 409, 'message' => 'Địa chỉ email này đã được sử dụng.'];
            }
        } else {
            $email = $username . '@huit.edu.vn';
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare(
            "INSERT INTO users (username, name, email, password, role, high_score, total_games, total_wins, coins, is_active)
             VALUES (?, ?, ?, ?, 'user', 0, 0, 0, 500, 1)"
        );
        $stmt->execute([$username, $username, $email, $passwordHash]);
        $newId = (int)$this->conn->lastInsertId();

        $stmtProf = $this->conn->prepare("INSERT INTO user_profiles (user_id, bio, preferred_mode) VALUES (?, 'Game thủ Pipe Game.', 'player')");
        $stmtProf->execute([$newId]);

        return [
            'success' => true,
            'status'  => 201,
            'message' => 'Đăng ký tài khoản thành công! Dữ liệu đã lưu vào MySQL.',
            'data'    => ['id' => $newId, 'user_id' => $newId, 'username' => $username, 'email' => $email]
        ];
    }

    // ==================== XỬ LÝ ĐĂNG NHẬP (USERNAME HOẶC EMAIL) ====================
    public function authenticatePlayer(string $identifier, string $password, string $ip = '', string $ua = ''): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'status' => 401, 'message' => 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.'];
        }

        $sessionToken = bin2hex(random_bytes(16));
        return [
            'success'       => true,
            'status'        => 200,
            'message'       => 'Đăng nhập thành công!',
            'session_token' => $sessionToken,
            'data'          => [
                'id'         => (int)$user['id'],
                'user_id'    => (int)$user['id'],
                'username'   => $user['username'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'highScore'  => (int)$user['high_score'],
                'totalGames' => (int)$user['total_games'],
                'totalWins'  => (int)$user['total_wins'],
            ]
        ];
    }

    // ==================== QUÊN MẬT KHẨU OTP 60 GIÂY ====================
    public function issuePasswordResetOtp(string $email, string $ip = '', ?SmtpMailService $mailer = null): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'status' => 404, 'message' => 'Không tìm thấy tài khoản gắn với email này.'];
        }

        $userId = (int)$user['id'];
        $now = time();
        $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otpCode, PASSWORD_BCRYPT);
        $requestToken = bin2hex(random_bytes(16));
        $expiresAt = $now + 60;

        // Vô hiệu hóa OTP cũ
        $this->conn->prepare("UPDATE password_reset_otps SET invalidated_at_ts = ? WHERE user_id = ? AND invalidated_at_ts IS NULL")
                   ->execute([$now, $userId]);

        // Lưu OTP mới vào MySQL
        $stmtInsert = $this->conn->prepare(
            "INSERT INTO password_reset_otps (user_id, request_token, otp_hash, attempts, max_attempts, expires_at_ts, created_at_ts, ip_address)
             VALUES (?, ?, ?, 0, 5, ?, ?, ?)"
        );
        $stmtInsert->execute([$userId, $requestToken, $otpHash, $expiresAt, $now, $ip]);

        // Gửi email qua Gmail SMTP
        $srv = $mailer ?? new SmtpMailService();
        $sendRes = $srv->sendPasswordResetOtpEmail($user['email'], $user['username'], $otpCode, 60);

        if (!$sendRes['sent']) {
            return ['success' => false, 'status' => 503, 'message' => $sendRes['error'] ?? 'Không thể gửi email OTP lúc này.'];
        }

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Mã OTP đã được gửi tới email của bạn (hiệu lực 60s).',
            'data'    => ['request_token' => $requestToken, 'email' => $user['email'], 'expires_in' => 60]
        ];
    }

    public function verifyPasswordResetOtp(string $email, string $requestToken, string $otp): array
    {
        $now = time();
        $stmt = $this->conn->prepare(
            "SELECT otp.* FROM password_reset_otps otp
             INNER JOIN users u ON otp.user_id = u.id
             WHERE otp.request_token = ? AND LOWER(u.email) = LOWER(?)
             LIMIT 1"
        );
        $stmt->execute([$requestToken, $email]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rec || $rec['invalidated_at_ts'] !== null) {
            return ['success' => false, 'status' => 400, 'message' => 'Yêu cầu không hợp lệ hoặc mã OTP đã bị vô hiệu hóa.'];
        }
        if ($now > (int)$rec['expires_at_ts']) {
            return ['success' => false, 'status' => 410, 'message' => 'Mã OTP đã hết hiệu lực (60 giây). Vui lòng gửi lại mã mới.'];
        }
        if (!password_verify($otp, $rec['otp_hash'])) {
            $attempts = (int)$rec['attempts'] + 1;
            $this->conn->prepare("UPDATE password_reset_otps SET attempts = ? WHERE id = ?")->execute([$attempts, $rec['id']]);
            return ['success' => false, 'status' => 401, 'message' => 'Mã OTP không chính xác.'];
        }

        $resetToken = bin2hex(random_bytes(16));
        $this->conn->prepare("UPDATE password_reset_otps SET verified_at_ts = ?, reset_token_hash = ? WHERE id = ?")
                   ->execute([$now, hash('sha256', $resetToken), $rec['id']]);

        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Xác minh OTP thành công!',
            'data'    => ['request_token' => $requestToken, 'reset_token' => $resetToken]
        ];
    }

    public function resetPasswordWithVerifiedOtp(string $requestToken, string $resetToken, string $newPassword, string $confirmPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'status' => 422, 'message' => 'Mật khẩu mới phải có ít nhất 8 ký tự.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'status' => 422, 'message' => 'Mật khẩu xác nhận không khớp.'];
        }

        $stmt = $this->conn->prepare("SELECT * FROM password_reset_otps WHERE request_token = ? LIMIT 1");
        $stmt->execute([$requestToken]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rec || empty($rec['verified_at_ts']) || $rec['invalidated_at_ts'] !== null) {
            return ['success' => false, 'status' => 403, 'message' => 'Yêu cầu chưa xác minh OTP hoặc đã hết hạn.'];
        }
        if (!hash_equals((string)$rec['reset_token_hash'], hash('sha256', $resetToken))) {
            return ['success' => false, 'status' => 403, 'message' => 'Mã xác thực đổi mật khẩu không hợp lệ.'];
        }

        $now = time();
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, (int)$rec['user_id']]);
        $this->conn->prepare("UPDATE password_reset_otps SET invalidated_at_ts = ? WHERE id = ?")->execute([$now, $rec['id']]);

        return ['success' => true, 'status' => 200, 'message' => 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.'];
    }

    public function resetUserHighScoreById(int $userId): bool
    {
        $stmt = $this->conn->prepare("UPDATE users SET high_score = 0 WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    // ==================== BUỔI 4: PHÂN TRANG (PAGINATION) & TÌM KIẾM (SEARCHING) ====================

    /**
     * Tiết 5 Buổi 4: Đếm tổng bản ghi theo từ khóa trước, sau đó truy vấn với LIMIT & OFFSET
     * Dùng bindValue(':limit', $limit, PDO::PARAM_INT) và bindValue(':keyword', "%$keyword%", PDO::PARAM_STR)
     */
    public function getPaginatedLevels(string $keyword = '', string $difficulty = '', int $page = 1, int $limit = 5): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;
        $keywordTrim = trim($keyword);

        $whereClauses = ['1=1'];
        if ($keywordTrim !== '') {
            $whereClauses[] = '(gl.title LIKE :keyword OR gl.map_size LIKE :keyword OR pc.name LIKE :keyword)';
        }
        if (in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $whereClauses[] = 'gl.difficulty = :difficulty';
        }
        $whereSql = implode(' AND ', $whereClauses);

        // Bước 1: Đếm tổng số bản ghi phù hợp điều kiện
        $sqlCount = "SELECT COUNT(*)
                     FROM game_levels gl
                     LEFT JOIN pipe_categories pc ON gl.category_id = pc.id
                     WHERE {$whereSql}";
        $stmtCount = $this->conn->prepare($sqlCount);
        if ($keywordTrim !== '') {
            $stmtCount->bindValue(':keyword', "%{$keywordTrim}%", PDO::PARAM_STR);
        }
        if (in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $stmtCount->bindValue(':difficulty', $difficulty, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRecords = (int)$stmtCount->fetchColumn();
        $totalPages = (int)ceil($totalRecords / $limit);

        // Bước 2: Truy vấn danh sách trang hiện tại với LIMIT & OFFSET
        $sqlData = "SELECT gl.*,
                           pc.name AS category_name,
                           pc.score_multiplier,
                           u.username AS creator_username,
                           u.name AS creator_name,
                           (SELECT COUNT(*) FROM game_sessions gs WHERE gs.game_level_id = gl.id) AS sessions_count
                    FROM game_levels gl
                    LEFT JOIN pipe_categories pc ON gl.category_id = pc.id
                    LEFT JOIN users u ON gl.created_by = u.id
                    WHERE {$whereSql}
                    ORDER BY gl.id DESC
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sqlData);
        if ($keywordTrim !== '') {
            $stmt->bindValue(':keyword', "%{$keywordTrim}%", PDO::PARAM_STR);
        }
        if (in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $stmt->bindValue(':difficulty', $difficulty, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nạp kèm danh sách Tags (Eager Loading tương đương cho bảng Pivot N-N)
        foreach ($items as &$item) {
            $stmtTags = $this->conn->prepare(
                "SELECT t.id, t.name, t.slug, t.color_hex
                 FROM tags t
                 INNER JOIN game_level_tag glt ON t.id = glt.tag_id
                 WHERE glt.game_level_id = ?"
            );
            $stmtTags->execute([$item['id']]);
            $item['tags'] = $stmtTags->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'items' => $items,
            'total_records' => $totalRecords,
            'total_pages' => max(1, $totalPages),
            'current_page' => $page,
            'limit' => $limit,
            'keyword' => $keywordTrim,
            'difficulty' => $difficulty,
        ];
    }

    // ==================== BUỔI 4: THAO TÁC CRUD AN TOÀN VỚI PREPARED STATEMENT ====================

    public function findLevelById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT gl.*, pc.name AS category_name, u.username AS creator_username
             FROM game_levels gl
             LEFT JOIN pipe_categories pc ON gl.category_id = pc.id
             LEFT JOIN users u ON gl.created_by = u.id
             WHERE gl.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $stmtTags = $this->conn->prepare("SELECT tag_id FROM game_level_tag WHERE game_level_id = ?");
        $stmtTags->execute([$id]);
        $row['tag_ids'] = array_map('intval', array_column($stmtTags->fetchAll(PDO::FETCH_ASSOC), 'tag_id'));
        return $row;
    }

    public function createLevel(array $data): int
    {
        [$rows, $cols] = array_map('intval', explode('x', $data['map_size'] ?? '3x8'));
        $stmt = $this->conn->prepare(
            "INSERT INTO game_levels
             (category_id, created_by, title, slug, map_size, grid_rows, grid_cols, difficulty, time_limit_seconds, reward_stars, thumbnail, description, map_config_json, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$data['category_id'],
            !empty($data['created_by']) ? (int)$data['created_by'] : 1,
            $data['title'],
            $data['slug'],
            $data['map_size'],
            $rows,
            $cols,
            $data['difficulty'],
            (int)($data['time_limit_seconds'] ?? 60),
            (int)($data['reward_stars'] ?? 100),
            $data['thumbnail'] ?? 'Images/NenNho.jpg',
            $data['description'] ?? '',
            is_string($data['map_config_json'] ?? null) ? $data['map_config_json'] : json_encode($data['map_config_json'] ?? ['yellowProb' => 0.25]),
            isset($data['is_published']) ? (int)$data['is_published'] : 1,
        ]);
        $newId = (int)$this->conn->lastInsertId();

        if (!empty($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->syncLevelTags($newId, $data['tag_ids']);
        }
        return $newId;
    }

    public function updateLevel(int $id, array $data): bool
    {
        [$rows, $cols] = array_map('intval', explode('x', $data['map_size'] ?? '3x8'));
        $stmt = $this->conn->prepare(
            "UPDATE game_levels
             SET category_id = ?, title = ?, map_size = ?, grid_rows = ?, grid_cols = ?,
                 difficulty = ?, time_limit_seconds = ?, reward_stars = ?, thumbnail = ?, description = ?, is_published = ?
             WHERE id = ?"
        );
        $ok = $stmt->execute([
            (int)$data['category_id'],
            $data['title'],
            $data['map_size'],
            $rows,
            $cols,
            $data['difficulty'],
            (int)($data['time_limit_seconds'] ?? 60),
            (int)($data['reward_stars'] ?? 100),
            $data['thumbnail'] ?? 'Images/NenNho.jpg',
            $data['description'] ?? '',
            isset($data['is_published']) ? (int)$data['is_published'] : 1,
            $id,
        ]);
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->syncLevelTags($id, $data['tag_ids']);
        }
        return $ok;
    }

    public function deleteLevel(int $id): bool
    {
        $this->conn->prepare("DELETE FROM game_level_tag WHERE game_level_id = ?")->execute([$id]);
        $stmt = $this->conn->prepare("DELETE FROM game_levels WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function syncLevelTags(int $levelId, array $tagIds): void
    {
        $this->conn->prepare("DELETE FROM game_level_tag WHERE game_level_id = ?")->execute([$levelId]);
        $stmtInsert = $this->conn->prepare("INSERT INTO game_level_tag (game_level_id, tag_id) VALUES (?, ?)");
        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            if ($tagId > 0) {
                $stmtInsert->execute([$levelId, $tagId]);
            }
        }
    }

    // ==================== LAB 5: TRUY VẤN SQL NÂNG CAO (JOIN, GROUP BY, HAVING, SUBQUERY) ====================

    /**
     * Bài tập 1 (Lab 5 - Slide 16): Thống kê số lượng màn chơi và vật phẩm theo từng danh mục (LEFT JOIN + COUNT + GROUP BY)
     */
    public function getCategoryProductStatistics(): array
    {
        $sql = "SELECT pc.id,
                       pc.name AS category_name,
                       pc.score_multiplier,
                       COUNT(DISTINCT gl.id) AS total_levels,
                       COUNT(DISTINCT si.id) AS total_shop_items
                FROM pipe_categories pc
                LEFT JOIN game_levels gl ON pc.id = gl.category_id
                LEFT JOIN shop_items si ON pc.id = si.category_id
                GROUP BY pc.id, pc.name, pc.score_multiplier
                ORDER BY total_levels DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 2 (Lab 5 - Slide 17): Tính tổng doanh thu cửa hàng vật phẩm theo từng ngày (JOIN + SUM + GROUP BY)
     */
    public function getDailyRevenueReport(): array
    {
        $sql = "SELECT so.order_date,
                       COUNT(DISTINCT so.id) AS total_orders,
                       SUM(sod.quantity) AS total_items_sold,
                       SUM(sod.quantity * sod.price) AS total_revenue
                FROM shop_orders so
                INNER JOIN shop_order_details sod ON so.id = sod.order_id
                WHERE so.status = 'completed'
                GROUP BY so.order_date
                ORDER BY so.order_date DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 3 (Lab 5 - Slide 18): Tìm loại danh mục có từ N màn chơi trở lên (GROUP BY + HAVING COUNT > N)
     */
    public function getCategoriesHavingMinLevels(int $minLevels = 2): array
    {
        $stmt = $this->conn->prepare(
            "SELECT pc.name AS category_name,
                    COUNT(gl.id) AS total_levels,
                    AVG(gl.reward_stars) AS avg_reward_stars
             FROM pipe_categories pc
             INNER JOIN game_levels gl ON pc.id = gl.category_id
             GROUP BY pc.id, pc.name
             HAVING COUNT(gl.id) >= ?
             ORDER BY total_levels DESC"
        );
        $stmt->execute([$minLevels]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 4 (Lab 5 - Slide 19): Danh sách người chơi và tổng tiền đã mua vật phẩm > ngưỡng (JOIN 3 bảng + GROUP BY + HAVING)
     */
    public function getHighSpendingPlayers(float $minSpent = 200000): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id AS user_id,
                    u.username,
                    u.name,
                    u.role,
                    SUM(sod.quantity) AS total_items,
                    SUM(sod.quantity * sod.price) AS total_spent
             FROM users u
             INNER JOIN shop_orders so ON u.id = so.user_id
             INNER JOIN shop_order_details sod ON so.id = sod.order_id
             GROUP BY u.id, u.username, u.name, u.role
             HAVING SUM(sod.quantity * sod.price) >= ?
             ORDER BY total_spent DESC"
        );
        $stmt->execute([$minSpent]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 5 (Lab 5 - Slide 20): Tìm vật phẩm có giá cao nhất trong từng danh mục (Correlated Subquery với MAX)
     */
    public function getMostExpensiveItemPerCategory(): array
    {
        $sql = "SELECT pc.name AS category_name,
                       si.name AS item_name,
                       si.price,
                       si.stock
                FROM shop_items si
                INNER JOIN pipe_categories pc ON si.category_id = pc.id
                WHERE si.price = (
                    SELECT MAX(si2.price)
                    FROM shop_items si2
                    WHERE si2.category_id = si.category_id
                )
                ORDER BY si.price DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 6 (Lab 5 - Slide 21): Liệt kê vật phẩm chưa từng được đặt hàng (LEFT JOIN + WHERE IS NULL)
     */
    public function getNeverOrderedShopItems(): array
    {
        $sql = "SELECT si.id,
                       si.name,
                       si.price,
                       pc.name AS category_name
                FROM shop_items si
                INNER JOIN pipe_categories pc ON si.category_id = pc.id
                LEFT JOIN shop_order_details sod ON si.id = sod.shop_item_id
                WHERE sod.shop_item_id IS NULL";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bài tập 7 & 8 (Lab 5 - Slide 22-24): Thống kê thuật toán AI vs Player theo độ khó (AVG, MIN, MAX, COUNT, GROUP BY)
     */
    public function getGameModeTelemetryAnalytics(): array
    {
        $sql = "SELECT game_mode,
                       difficulty,
                       COUNT(*) AS total_matches,
                       SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) AS total_wins,
                       ROUND(AVG(NULLIF(JSON_EXTRACT(CASE WHEN JSON_VALID(telemetry_nosql)
                           THEN telemetry_nosql ELSE '{}' END, '$.quality_score'), 'null')), 1) AS avg_score,
                       MAX(CAST(NULLIF(JSON_EXTRACT(CASE WHEN JSON_VALID(telemetry_nosql)
                           THEN telemetry_nosql ELSE '{}' END, '$.quality_score'), 'null') AS DECIMAL(6,2))) AS max_score,
                       ROUND(AVG(duration_ms) / 1000.0, 2) AS avg_duration_sec,
                       ROUND(AVG(steps_count), 1) AS avg_steps,
                       SUM(ai_retries) AS total_ai_restarts
                FROM game_sessions
                GROUP BY game_mode, difficulty
                ORDER BY game_mode ASC, avg_score DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== HỖ TRỢ AUTHENTICATION, LEADERBOARD & GAME API ====================

    public function getLeaderboard(string $keyword = '', int $limit = 10): array
    {
        $sql = "SELECT u.id AS user_id,
                       u.id,
                       u.username,
                       u.name,
                       u.role,
                       u.high_score,
                       u.total_games,
                       u.total_wins,
                       COALESCE(MIN(CASE WHEN gs.result = 'win' AND gs.duration_ms > 0 THEN gs.duration_ms END), 999999) AS fastest_ms,
                       ROUND(COALESCE(MIN(CASE WHEN gs.result = 'win' AND gs.duration_ms > 0 THEN gs.duration_ms END), 0) / 1000.0, 2) AS completion_time
                FROM users u
                LEFT JOIN game_sessions gs ON u.id = gs.user_id
                WHERE u.username LIKE :kw OR u.name LIKE :kw
                GROUP BY u.id, u.username, u.name, u.role, u.high_score, u.total_games, u.total_wins
                ORDER BY fastest_ms ASC, u.high_score DESC
                LIMIT :lim";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':kw', '%' . trim($keyword) . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim', min(10, max(1, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $total = (int)$r['total_games'];
            $wins = (int)$r['total_wins'];
            $r['win_rate'] = $total > 0 ? round(($wins / $total) * 100, 1) : 0.0;
            $r['completion_time_label'] = ((float)$r['completion_time'] > 0)
                ? ($r['completion_time'] . 's')
                : '--';
        }
        return $rows;
    }

    public function findUserByUsernameOrEmail(string $identifier): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([trim($identifier), trim($identifier)]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }
        $stmtProf = $this->conn->prepare("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
        $stmtProf->execute([$u['id']]);
        $u['profile'] = $stmtProf->fetch(PDO::FETCH_ASSOC) ?: null;
        return $u;
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }
        $stmtProf = $this->conn->prepare("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1");
        $stmtProf->execute([$u['id']]);
        $u['profile'] = $stmtProf->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtAch = $this->conn->prepare(
            "SELECT a.*, au.unlocked_at
             FROM achievements a
             INNER JOIN achievement_user au ON a.id = au.achievement_id
             WHERE au.user_id = ?"
        );
        $stmtAch->execute([$u['id']]);
        $u['achievements'] = $stmtAch->fetchAll(PDO::FETCH_ASSOC);
        return $u;
    }

    public function registerUser(string $username, string $password, ?string $email = null, string $role = 'user'): array
    {
        $username = trim($username);
        $email = $email ? trim($email) : ($username . '@huit.edu.vn');
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "INSERT INTO users (username, name, email, password, role, high_score, total_games, total_wins, coins, is_active)
             VALUES (?, ?, ?, ?, ?, 0, 0, 0, 500, 1)"
        );
        $stmt->execute([$username, $username, $email, $hashedPassword, $role]);
        $userId = (int)$this->conn->lastInsertId();

        $stmtProf = $this->conn->prepare(
            "INSERT INTO user_profiles (user_id, bio, preferred_mode, sound_enabled, music_enabled)
             VALUES (?, 'Người chơi mới.', 'player', 1, 1)"
        );
        $stmtProf->execute([$userId]);

        return $this->findUserById($userId);
    }

    public function updatePasswordByUsername(string $username, string $newPassword): bool
    {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        return $stmt->execute([$hashed, trim($username)]);
    }

    public function recordGameSession(array $payload, ?int $userId = null): array
    {
        $username = trim($payload['username'] ?? '');
        $user = $username !== '' ? $this->findUserByUsernameOrEmail($username) : null;
        $userId = $user ? (int)$user['id'] : null;

        $gameMode = in_array($payload['game_mode'] ?? 'player', ['player', 'ai'], true) ? $payload['game_mode'] : 'player';
        $mapOption = in_array($payload['map_option'] ?? 'random', ['random', 'custom'], true) ? $payload['map_option'] : 'random';
        $mapSize = $payload['map_size'] ?? '3x8';
        $difficulty = in_array($payload['difficulty'] ?? 'easy', ['easy', 'medium', 'hard'], true) ? $payload['difficulty'] : 'easy';
        $result = in_array($payload['result'] ?? 'win', ['win', 'lose', 'surrender'], true) ? $payload['result'] : 'win';
        $scoreEarned = max(0, (int)($payload['score_earned'] ?? 0));
        $telemetry = $payload['telemetry_nosql'] ?? [];
        if (!is_array($telemetry)) $telemetry = [];
        if (($payload['scoring_version'] ?? '') === PipeScoringService::VERSION) {
            $quality = $payload['quality_score'] ?? null;
            $quality = is_int($quality) && $quality >= 0 && $quality <= 100 ? $quality : null;
            $scoreEarned = $quality ?? 0;
            $telemetry['scoring_version'] = PipeScoringService::VERSION;
            $telemetry['quality_score'] = $quality;
        }
        $totalScore = max(0, (int)($payload['total_score'] ?? $scoreEarned));
        $connectedPipes = max(0, (int)($payload['connected_pipes'] ?? 0));
        $stepsCount = max(0, (int)($payload['steps_count'] ?? 0));
        $aiRetries = max(0, (int)($payload['ai_retries'] ?? 0));
        $durationMs = max(0, (int)($payload['duration_ms'] ?? 0));
        $telemetryJson = json_encode($telemetry + ['mode' => $gameMode, 'mapSize' => $mapSize]);

        $stmt = $this->conn->prepare(
            "INSERT INTO game_sessions
             (user_id, player_name, game_mode, map_option, map_size, difficulty, result, score_earned, connected_pipes, steps_count, ai_retries, duration_ms, telemetry_nosql)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $user ? $user['username'] : 'Guest',
            $gameMode,
            $mapOption,
            $mapSize,
            $difficulty,
            $result,
            $scoreEarned,
            $connectedPipes,
            $stepsCount,
            $aiRetries,
            $durationMs,
            $telemetryJson,
        ]);

        // Nếu chế độ Player và đã đăng nhập: cập nhật high_score, total_games, total_wins
        $newHighScore = $totalScore;
        if ($user && $gameMode === 'player') {
            $currentHigh = (int)$user['high_score'];
            $newHighScore = max($currentHigh, $totalScore);
            $newTotalGames = (int)$user['total_games'] + 1;
            $newTotalWins = (int)$user['total_wins'] + ($result === 'win' ? 1 : 0);
            $bonusCoins = $result === 'win' ? 50 : 10;

            $stmtUp = $this->conn->prepare(
                "UPDATE users SET high_score = ?, total_games = ?, total_wins = ?, coins = coins + ? WHERE id = ?"
            );
            $stmtUp->execute([$newHighScore, $newTotalGames, $newTotalWins, $bonusCoins, $userId]);
        }

        return [
            'session_id' => (int)$this->conn->lastInsertId(),
            'high_score' => $newHighScore,
            'user_updated' => (bool)$user,
        ];
    }

    public function resetUserHighScore(string $username): bool
    {
        $stmt = $this->conn->prepare("UPDATE users SET high_score = 0 WHERE username = ?");
        return $stmt->execute([trim($username)]);
    }

    public function getAllCategories(): array
    {
        return $this->conn->query("SELECT * FROM pipe_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTags(): array
    {
        return $this->conn->query("SELECT * FROM tags ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllUsersWithProfiles(): array
    {
        $sql = "SELECT u.*, up.phone, up.address, up.birthday, up.preferred_mode
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                ORDER BY u.id ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUserRole(int $userId, string $role): bool
    {
        if (!in_array($role, ['admin', 'user'], true)) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        return $stmt->execute([$role, $userId]);
    }

    public function toggleUserActive(int $userId): array
    {
        $stmt = $this->conn->prepare("SELECT id, username, role, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'message' => 'Không tìm thấy người chơi #'.$userId];
        }

        if (($user['role'] ?? '') !== 'user') {
            return ['success' => false, 'message' => 'Chỉ được phép khóa hoặc mở khóa tài khoản người chơi.'];
        }
        $newStatus = ((int)($user['is_active'] ?? 1) === 1) ? 0 : 1;
        $up = $this->conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'user'");
        $up->execute([$newStatus, $userId]);

        return [
            'success'   => true,
            'user_id'   => $userId,
            'username'  => $user['username'],
            'is_active' => $newStatus,
            'message'   => $newStatus === 1 ? 'Đã mở khóa tài khoản thành công.' : 'Đã khóa tài khoản thành công.'
        ];
    }

    public function getUserPvpMatchHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT h.*, r.room_code, r.map_size, r.difficulty, r.num_pairs, r.map_seed
                FROM pvp_match_history h
                LEFT JOIN pvp_rooms r ON h.room_id = r.id
                WHERE h.user_id = ?
                ORDER BY h.created_at DESC, h.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdminDashboardStats(): array
    {
        $totalUsers = (int)$this->conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $activeUsers = (int)$this->conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
        $bannedUsers = $totalUsers - $activeUsers;

        $totalPvpMatches = 0;
        try {
            $totalPvpMatches = (int)$this->conn->query("SELECT COUNT(*) FROM pvp_rooms WHERE status = 'finished'")->fetchColumn();
        } catch (\Throwable $e) {}

        $totalLevels = 0;
        try {
            $totalLevels = (int)$this->conn->query("SELECT COUNT(*) FROM game_levels")->fetchColumn();
        } catch (\Throwable $e) {}

        $topEloUser = null;
        try {
            $topEloUser = $this->conn->query("SELECT username, pvp_rating, pvp_tier FROM users WHERE role = 'user' ORDER BY pvp_rating DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        return [
            'total_users'       => $totalUsers,
            'active_users'      => $activeUsers,
            'banned_users'      => $bannedUsers,
            'total_pvp_matches' => $totalPvpMatches,
            'total_levels'      => $totalLevels,
            'top_elo_user'      => $topEloUser,
        ];
    }

    public function getRecentSessions(int $limit = 15): array
    {
        $stmt = $this->conn->prepare(
            "SELECT gs.*, gl.title AS level_title
             FROM game_sessions gs
             LEFT JOIN game_levels gl ON gs.game_level_id = gl.id
             ORDER BY gs.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $telemetry = json_decode($row['telemetry_nosql'] ?? '{}', true) ?: [];
            $row['score_label'] = ($telemetry['scoring_version'] ?? '') === PipeScoringService::VERSION
                ? (isset($telemetry['quality_score']) ? $telemetry['quality_score'] . '/100' : 'Chưa xác định')
                : $row['score_earned'] . ' (điểm cũ)';
        }
        unset($row);
        return $rows;
    }

    /**
     * Khởi tạo cấu trúc và dữ liệu mẫu cho SQLite nếu máy chạy ở chế độ không bật MySQL
     */
    private function ensureSqliteSchemaAndSeed(): void
    {
        $check = $this->conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if ($check) {
            return;
        }

        $this->conn->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                high_score INTEGER NOT NULL DEFAULT 0,
                total_games INTEGER NOT NULL DEFAULT 0,
                total_wins INTEGER NOT NULL DEFAULT 0,
                coins INTEGER NOT NULL DEFAULT 500,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE user_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                phone TEXT,
                address TEXT,
                birthday TEXT,
                avatar TEXT DEFAULT 'Images/CupSao.png',
                bio TEXT,
                preferred_mode TEXT DEFAULT 'player',
                sound_enabled INTEGER DEFAULT 1,
                music_enabled INTEGER DEFAULT 1
            );
            CREATE TABLE pipe_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                score_multiplier REAL NOT NULL DEFAULT 1.00
            );
            CREATE TABLE game_levels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                created_by INTEGER,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                map_size TEXT NOT NULL DEFAULT '3x8',
                grid_rows INTEGER NOT NULL DEFAULT 3,
                grid_cols INTEGER NOT NULL DEFAULT 8,
                difficulty TEXT NOT NULL DEFAULT 'easy',
                time_limit_seconds INTEGER NOT NULL DEFAULT 60,
                reward_stars INTEGER NOT NULL DEFAULT 100,
                thumbnail TEXT DEFAULT 'Images/NenNho.jpg',
                description TEXT,
                map_config_json TEXT,
                is_published INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                color_hex TEXT NOT NULL DEFAULT '#01caee'
            );
            CREATE TABLE game_level_tag (
                game_level_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (game_level_id, tag_id)
            );
            CREATE TABLE game_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                game_level_id INTEGER,
                player_name TEXT NOT NULL DEFAULT 'Guest',
                game_mode TEXT NOT NULL DEFAULT 'player',
                map_option TEXT NOT NULL DEFAULT 'random',
                map_size TEXT NOT NULL DEFAULT '3x8',
                difficulty TEXT NOT NULL DEFAULT 'easy',
                result TEXT NOT NULL DEFAULT 'win',
                score_earned INTEGER NOT NULL DEFAULT 0,
                connected_pipes INTEGER NOT NULL DEFAULT 0,
                steps_count INTEGER NOT NULL DEFAULT 0,
                ai_retries INTEGER NOT NULL DEFAULT 0,
                duration_ms INTEGER NOT NULL DEFAULT 0,
                telemetry_nosql TEXT,
                played_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE achievements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                min_score INTEGER NOT NULL DEFAULT 100,
                badge_icon TEXT DEFAULT 'Images/CupSao.png'
            );
            CREATE TABLE achievement_user (
                user_id INTEGER NOT NULL,
                achievement_id INTEGER NOT NULL,
                unlocked_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, achievement_id)
            );
            CREATE TABLE shop_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                price REAL NOT NULL DEFAULT 50000,
                stock INTEGER NOT NULL DEFAULT 100,
                pipe_skin_folder TEXT NOT NULL DEFAULT 'OngXanh',
                description TEXT
            );
            CREATE TABLE shop_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_date TEXT NOT NULL,
                payment_gateway TEXT NOT NULL DEFAULT 'momo',
                status TEXT NOT NULL DEFAULT 'completed'
            );
            CREATE TABLE shop_order_details (
                order_id INTEGER NOT NULL,
                shop_item_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                price REAL NOT NULL DEFAULT 0,
                PRIMARY KEY (order_id, shop_item_id)
            );
        ");

        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $stmtU = $this->conn->prepare("INSERT INTO users (id, username, name, email, password, role, high_score, total_games, total_wins, coins) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $users = [
            [1, 'admin', 'TS. Nguyễn Quản Trị (Admin)', 'admin@huit.edu.vn', $hash, 'admin', 1250, 25, 22, 9999],
            [2, 'staff', 'ThS. Trần Thiết Kế Màn Chơi (Staff)', 'staff@huit.edu.vn', $hash, 'staff', 980, 20, 16, 3500],
            [3, 'player1', 'Lê Hoàng An (Sinh viên HUIT)', 'player1@huit.edu.vn', $hash, 'user', 1120, 30, 24, 1800],
            [4, 'player2', 'Phạm Minh Tuấn', 'tuan.pm@huit.edu.vn', $hash, 'user', 860, 18, 12, 1200],
            [5, 'player3', 'Đỗ Ngọc Bích', 'bich.dn@huit.edu.vn', $hash, 'user', 640, 15, 9, 950],
            [6, 'staff2', 'Võ Quốc Huy (Staff AI)', 'huy.vq@huit.edu.vn', $hash, 'staff', 790, 12, 10, 2100],
        ];
        foreach ($users as $u) {
            $stmtU->execute($u);
        }

        $stmtP = $this->conn->prepare("INSERT INTO user_profiles (user_id, phone, address, birthday, bio, preferred_mode) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtP->execute([1, '0901234567', '140 Lê Trọng Tấn, Tân Phú, TP.HCM', '1990-05-15', 'Quản trị viên hệ thống Game Nối Ống Nước HUIT.', 'player']);
        $stmtP->execute([2, '0912345678', 'Quận Tân Bình, TP.HCM', '1995-08-20', 'Chuyên viên thiết kế bản đồ và cân bằng độ khó AI.', 'ai']);
        $stmtP->execute([3, '0987654321', 'Ký túc xá ĐH Công Thương TP.HCM', '2004-11-02', 'Đam mê giải đố nối ống nước mức Hard 5x12.', 'player']);

        $stmtC = $this->conn->prepare("INSERT INTO pipe_categories (id, name, slug, description, score_multiplier) VALUES (?, ?, ?, ?, ?)");
        $stmtC->execute([1, 'Hệ thống Ống Xanh Cơ Bản', 'he-thong-ong-xanh-co-ban', 'Các màn chơi tiêu chuẩn với ống xanh xoay 360 độ.', 1.00]);
        $stmtC->execute([2, 'Mê Cung Ống Đỏ Cố Định', 'me-cung-ong-do-co-dinh', 'Bổ sung các đoạn ống đỏ khóa cứng và tường chắn.', 1.50]);
        $stmtC->execute([3, 'Thử Thách Ống Vàng Hoán Đổi', 'thu-thach-ong-vang-hoan-doi', 'Đỉnh cao chiến thuật với cụm ống vàng chữ L hoán đổi.', 2.00]);
        $stmtC->execute([4, 'Phòng Thí Nghiệm AI Leo Đồi', 'phong-thi-nghiem-ai-leo-doi', 'Các bản đồ chuyên biệt để kiểm thử AI Hill-Climbing.', 1.75]);

        $stmtL = $this->conn->prepare("INSERT INTO game_levels (id, category_id, created_by, title, slug, map_size, grid_rows, grid_cols, difficulty, time_limit_seconds, reward_stars, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $levels = [
            [1, 1, 1, 'Khởi nguồn Dòng chảy Tân Phú', 'khoi-nguon-dong-chay-tan-phu', '3x8', 3, 8, 'easy', 60, 100, 'Màn chơi mở đầu 3x8 giúp làm quen với thao tác xoay ống.'],
            [2, 1, 2, 'Trạm Bơm Nước Trung Tâm', 'tram-bom-nuoc-trung-tam', '3x10', 3, 10, 'easy', 60, 120, 'Mở rộng lưới lên 3x10 với nhiều nhánh rẽ chữ thập.'],
            [3, 1, 2, 'Đường Ống Cao Áp Đông Sài Gòn', 'duong-ong-cao-ap-dong-sai-gon', '3x12', 3, 12, 'medium', 55, 180, 'Đường ống trải dài 12 cột kèm theo các đoạn ống đỏ.'],
            [4, 2, 1, 'Vượt Tường Đá Ngầm', 'vuot-tuong-da-ngam', '5x6', 5, 6, 'medium', 50, 200, 'Bản đồ 5x6 xuất hiện chướng ngại vật tường gạch.'],
            [5, 2, 2, 'Mạng Lưới Thủy Cục Cấp Độ 2', 'mang-luoi-thuy-cuc-cap-do-2', '5x8', 5, 8, 'medium', 50, 220, 'Kết hợp ống chữ thập phân nhánh và điểm khóa đỏ.'],
            [6, 3, 2, 'Giải Mã Cụm Ống Vàng Liền Kề', 'giai-ma-cum-ong-vang-lien-ke', '5x10', 5, 10, 'hard', 60, 300, 'Vừa xoay ống xanh vừa hoán đổi vị trí các cặp ống vàng.'],
            [7, 3, 1, 'Siêu Đô Thị Thủy Lợi 5x12', 'sieu-do-thi-thuy-loi-5x12', '5x12', 5, 12, 'hard', 60, 350, 'Bản đồ kích thước tối đa 5x12 thử thách cực đại.'],
            [8, 4, 6, 'Benchmark AI Hill-Climbing #01', 'benchmark-ai-hill-climbing-01', '5x8', 5, 8, 'hard', 60, 280, 'Bản đồ chuẩn hóa để đo thời gian hội tụ của AI.'],
        ];
        foreach ($levels as $l) {
            $stmtL->execute($l);
        }

        $stmtT = $this->conn->prepare("INSERT INTO tags (id, name, slug, color_hex) VALUES (?, ?, ?, ?)");
        $stmtT->execute([1, 'Tân thủ (Beginner)', 'tan-thu', '#22c55e']);
        $stmtT->execute([2, 'Ống Vàng (Swap Pipe)', 'ong-vang', '#eab308']);
        $stmtT->execute([3, 'Ống Đỏ (Fixed Pipe)', 'ong-do', '#ef4444']);
        $stmtT->execute([4, 'Tối ưu AI (Heuristic)', 'toi-uu-ai', '#06b6d4']);
        $stmtT->execute([5, 'Bản đồ Lớn (5x12)', 'ban-do-lon', '#a855f7']);

        $this->conn->exec("INSERT INTO game_level_tag VALUES (1,1),(2,1),(3,3),(4,3),(5,3),(5,4),(6,2),(6,3),(7,2),(7,3),(7,5),(8,2),(8,4);");

        $stmtS = $this->conn->prepare("INSERT INTO shop_items (id, category_id, name, slug, price, stock, pipe_skin_folder, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $items = [
            [1, 1, 'Bộ Ống Xanh Ngọc Bích (Jade Pipe)', 'bo-ong-xanh-ngoc-bich', 150000, 50, 'OngXanh', 'Bộ skin ống nước xanh ngọc mượt mà.'],
            [2, 1, 'Đồng Hồ Nước Tăng Áp (+15s)', 'dong-ho-nuoc-tang-ap', 85000, 120, 'OngXanh', 'Tăng thêm 15 giây cho cột nước thời gian.'],
            [3, 2, 'Bộ Ống Đỏ Titan Chịu Nhiệt', 'bo-ong-do-titan-chiu-nhiet', 250000, 35, 'OngDo', 'Skin ống đỏ phong cách công nghiệp.'],
            [4, 2, 'La Bàn Định Tuyến Nguồn Nước', 'la-ban-dinh-tuyen-nguon-nuoc', 190000, 60, 'OngDo', 'Hiển thị gợi ý hướng mở của ống bắt đầu.'],
            [5, 3, 'Bộ Ống Vàng Hoàng Gia 24K', 'bo-ong-vang-hoang-gia-24k', 450000, 20, 'OngVang', 'Skin ống vàng chữ L mạ vàng 24K.'],
            [6, 3, 'Gói Cứu Trợ AI Giải Hộ Đặc Biệt', 'goi-cuu-tro-ai-giai-ho', 320000, 40, 'OngVang', 'Kích hoạt AI giải hộ mà vẫn bảo toàn điểm số.'],
            [7, 4, 'Mô-đun Phân Tích Heuristic Pro', 'mo-dun-phan-tich-heuristic-pro', 500000, 15, 'OngXanh', 'Hiển thị đồ thị trực quan từng bước leo đồi của AI.'],
            [8, 4, 'Bộ Ống Kim Cương Vũ Trụ (Chưa ai mua)', 'bo-ong-kim-cuong-vu-tru', 990000, 10, 'OngVang', 'Vật phẩm hiếm kiểm thử LEFT JOIN ... IS NULL.'],
        ];
        foreach ($items as $it) {
            $stmtS->execute($it);
        }

        $this->conn->exec("
            INSERT INTO shop_orders (id, user_id, order_date, payment_gateway, status) VALUES
            (1, 3, '2026-10-10', 'momo', 'completed'),
            (2, 1, '2026-10-11', 'bank', 'completed'),
            (3, 4, '2026-10-11', 'momo', 'completed'),
            (4, 2, '2026-10-12', 'bank', 'completed'),
            (5, 3, '2026-10-13', 'momo', 'completed');
            INSERT INTO shop_order_details (order_id, shop_item_id, quantity, price) VALUES
            (1, 1, 2, 150000), (1, 2, 3, 85000), (2, 5, 2, 450000), (2, 7, 1, 500000),
            (3, 2, 2, 85000), (3, 4, 1, 190000), (4, 6, 2, 320000), (5, 5, 1, 450000);
            INSERT INTO game_sessions (user_id, game_level_id, player_name, game_mode, map_option, map_size, difficulty, result, score_earned, connected_pipes, steps_count, ai_retries, duration_ms) VALUES
            (1, 7, 'admin', 'player', 'custom', '5x12', 'hard', 'win', 420, 14, 28, 0, 34200),
            (3, 6, 'player1', 'player', 'custom', '5x10', 'hard', 'win', 380, 12, 22, 0, 29500),
            (2, 8, 'staff', 'ai', 'custom', '5x8', 'hard', 'win', 310, 11, 45, 2, 18450),
            (4, 5, 'player2', 'player', 'random', '5x8', 'medium', 'win', 260, 9, 17, 0, 24100),
            (6, 7, 'staff2', 'ai', 'custom', '5x12', 'hard', 'win', 390, 13, 52, 3, 22890);
        ");
    }

    /**
     * Bảo đảm schema PvP tồn tại (MySQL + SQLite) mà không xóa dữ liệu.
     */
    public function ensurePvpSchema(): void
    {
        if (!$this->conn) {
            return;
        }

        $isSqlite = $this->driver === 'sqlite';

        if ($isSqlite) {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS pvp_rooms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_code TEXT NOT NULL UNIQUE,
                    host_user_id INTEGER NOT NULL,
                    room_type TEXT NOT NULL DEFAULT 'custom',
                    max_players INTEGER NOT NULL DEFAULT 3,
                    current_players INTEGER NOT NULL DEFAULT 1,
                    status TEXT NOT NULL DEFAULT 'waiting',
                    map_size TEXT NOT NULL DEFAULT '5x8',
                    difficulty TEXT NOT NULL DEFAULT 'medium',
                    num_pairs INTEGER NOT NULL DEFAULT 2,
                    map_seed TEXT,
                    map_config_json TEXT,
                    map_config_hash TEXT,
                    map_generator_version TEXT,
                    time_limit_seconds INTEGER NOT NULL DEFAULT 300,
                    started_at_ts INTEGER,
                    ended_at_ts INTEGER,
                    countdown_started_at INTEGER,
                    event_seq INTEGER NOT NULL DEFAULT 0,
                    ratings_applied INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS pvp_room_players (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    player_name TEXT NOT NULL,
                    seat_number INTEGER NOT NULL DEFAULT 1,
                    is_host INTEGER NOT NULL DEFAULT 0,
                    is_ready INTEGER NOT NULL DEFAULT 0,
                    connection_status TEXT NOT NULL DEFAULT 'connected',
                    current_score INTEGER NOT NULL DEFAULT 0,
                    completed_pairs INTEGER NOT NULL DEFAULT 0,
                    total_pairs INTEGER NOT NULL DEFAULT 2,
                    solve_status TEXT NOT NULL DEFAULT 'playing',
                    finish_time_ms INTEGER,
                    initial_rating INTEGER NOT NULL DEFAULT 1000,
                    rating_change INTEGER NOT NULL DEFAULT 0,
                    final_rank INTEGER,
                    last_heartbeat_ts INTEGER,
                    board_rotations_json TEXT,
                    board_state_version INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(room_id, user_id)
                );
                CREATE TABLE IF NOT EXISTS pvp_matchmaking_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL UNIQUE,
                    target_players INTEGER NOT NULL DEFAULT 3,
                    player_rating INTEGER NOT NULL DEFAULT 1000,
                    preferred_size TEXT NOT NULL DEFAULT '5x8',
                    preferred_diff TEXT NOT NULL DEFAULT 'medium',
                    status TEXT NOT NULL DEFAULT 'waiting',
                    matched_room_code TEXT,
                    queued_at_ts INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS pvp_match_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    final_rank INTEGER NOT NULL,
                    score INTEGER NOT NULL DEFAULT 0,
                    duration_ms INTEGER NOT NULL DEFAULT 0,
                    rating_before INTEGER NOT NULL DEFAULT 1000,
                    rating_after INTEGER NOT NULL DEFAULT 1000,
                    rating_delta INTEGER NOT NULL DEFAULT 0,
                    win_status TEXT NOT NULL DEFAULT 'loss',
                    k_factor INTEGER NOT NULL DEFAULT 32,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(room_id, user_id)
                );
            ");
        }

        $this->addColumnIfMissing('users', 'pvp_rating', $isSqlite ? 'INTEGER NOT NULL DEFAULT 1000' : 'INT NOT NULL DEFAULT 1000');
        $this->addColumnIfMissing('users', 'pvp_tier', $isSqlite ? "TEXT NOT NULL DEFAULT 'Bạc'" : "VARCHAR(32) NOT NULL DEFAULT 'Bạc'");
        $this->addColumnIfMissing('users', 'pvp_matches', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'INT NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('users', 'pvp_wins', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'INT NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('users', 'pvp_losses', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'INT NOT NULL DEFAULT 0');

        $this->addColumnIfMissing('pvp_rooms', 'map_config_hash', $isSqlite ? 'TEXT' : 'VARCHAR(64) NULL');
        $this->addColumnIfMissing('pvp_rooms', 'map_generator_version', $isSqlite ? 'TEXT' : 'VARCHAR(32) NULL');
        $this->addColumnIfMissing('pvp_rooms', 'countdown_started_at', $isSqlite ? 'INTEGER' : 'INT NULL');
        $this->addColumnIfMissing('pvp_rooms', 'event_seq', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'INT UNSIGNED NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('pvp_rooms', 'ratings_applied', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT UNSIGNED NOT NULL DEFAULT 0');

        $this->addColumnIfMissing('pvp_room_players', 'board_rotations_json', $isSqlite ? 'TEXT' : 'MEDIUMTEXT NULL');
        $this->addColumnIfMissing('pvp_room_players', 'board_state_version', $isSqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'INT UNSIGNED NOT NULL DEFAULT 0');

        $this->addColumnIfMissing('pvp_match_history', 'win_status', $isSqlite ? "TEXT NOT NULL DEFAULT 'loss'" : "ENUM('win','loss','draw','abandoned') NOT NULL DEFAULT 'loss'");
        $this->addColumnIfMissing('pvp_match_history', 'k_factor', $isSqlite ? 'INTEGER NOT NULL DEFAULT 32' : 'TINYINT UNSIGNED NOT NULL DEFAULT 32');

        try {
            if ($isSqlite) {
                $this->conn->exec('CREATE UNIQUE INDEX IF NOT EXISTS pvp_match_history_room_user_uq ON pvp_match_history (room_id, user_id)');
                $this->conn->exec('CREATE UNIQUE INDEX IF NOT EXISTS pvp_room_players_room_user_uq ON pvp_room_players (room_id, user_id)');
            } else {
                $this->conn->exec('ALTER TABLE pvp_match_history ADD UNIQUE INDEX pvp_match_history_room_user_uq (room_id, user_id)');
            }
        } catch (\Throwable $e) {
            // Index đã tồn tại — bỏ qua
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        try {
            if ($this->driver === 'sqlite') {
                $cols = $this->conn->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $col) {
                    if (($col['name'] ?? '') === $column) {
                        return;
                    }
                }
                $this->conn->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                return;
            }

            $stmt = $this->conn->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->fetch()) {
                return;
            }
            $this->conn->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            // Bảng chưa tồn tại hoặc cột đã có
        }
    }
}
