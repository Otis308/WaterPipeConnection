<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdoDatabaseService;
use App\Services\SmtpMailService;

class GameApiController extends Controller
{
    private PdoDatabaseService $db;

    public function __construct(?PdoDatabaseService $db = null)
    {
        $this->db = $db ?? new PdoDatabaseService();
    }

    private function getJsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?: [];
    }

    private function jsonResponse(bool $success, string $message, mixed $data = null, int $statusCode = 200, array $extra = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = array_merge([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $extra);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==================== 1. ĐĂNG KÝ (POST /api/auth/register) ====================
    public function register(): void
    {
        $payload = $this->getJsonPayload();
        $username = trim((string)($payload['username'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirmPassword = (string)($payload['confirmPassword'] ?? ($payload['password_confirmation'] ?? $password));

        if ($username === '' || $password === '') {
            $this->jsonResponse(false, 'Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!', null, 422);
        }
        if ($email === '') {
            $email = $username . '@huit.edu.vn';
        }

        $result = $this->db->registerPlayerAccount([
            'username'        => $username,
            'email'           => $email,
            'password'        => $password,
            'confirmPassword' => $confirmPassword,
        ]);

        $this->jsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? null,
            $result['status'] ?? ($result['success'] ? 201 : 400),
            isset($result['errors']) ? ['errors' => $result['errors']] : []
        );
    }

    // ==================== 2. ĐĂNG NHẬP (POST /api/auth/login) ====================
    public function login(): void
    {
        $payload = $this->getJsonPayload();
        $identifier = trim((string)($payload['identifier'] ?? ($payload['username'] ?? ($payload['email'] ?? ''))));
        $password = (string)($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->jsonResponse(false, 'Vui lòng nhập Tên đăng nhập/Email và Mật khẩu!', null, 422);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $result = $this->db->authenticatePlayer($identifier, $password, $ip, $ua);

        if (!$result['success']) {
            $this->jsonResponse(false, $result['message'], null, $result['status'] ?? 401);
        }

        $_SESSION['huit_auth_user'] = $result['data'];
        $_SESSION['huit_auth_token'] = $result['session_token'] ?? bin2hex(random_bytes(16));

        $this->jsonResponse(true, $result['message'], $result['data'], 200, [
            'auth_token' => $_SESSION['huit_auth_token'],
        ]);
    }

    // ==================== 3. THÔNG TIN PHIÊN & ĐĂNG XUẤT ====================
    public function me(): void
    {
        if (empty($_SESSION['huit_auth_user'])) {
            $this->jsonResponse(false, 'Chưa đăng nhập.', null, 401);
        }
        $this->jsonResponse(true, 'Phiên đăng nhập hợp lệ.', $_SESSION['huit_auth_user']);
    }

    public function logout(): void
    {
        unset($_SESSION['huit_auth_user'], $_SESSION['huit_auth_token']);
        $this->jsonResponse(true, 'Đã đăng xuất thành công.');
    }

    // ==================== 4. QUÊN MẬT KHẨU OTP (60 GIÂY) ====================
    public function sendForgotPasswordOtp(): void
    {
        $payload = $this->getJsonPayload();
        $email = trim((string)($payload['email'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $result = $this->db->issuePasswordResetOtp($email, $ip, new SmtpMailService());
        $extra = isset($result['retry_after']) ? ['retry_after' => $result['retry_after']] : [];
        $this->jsonResponse($result['success'], $result['message'], $result['data'] ?? null, $result['status'] ?? 200, $extra);
    }

    public function verifyForgotPasswordOtp(): void
    {
        $payload = $this->getJsonPayload();
        $email = trim((string)($payload['email'] ?? ''));
        $requestToken = trim((string)($payload['request_token'] ?? ''));
        $otp = trim((string)($payload['otp'] ?? ''));

        $result = $this->db->verifyPasswordResetOtp($email, $requestToken, $otp);
        $extra = isset($result['remaining_attempts']) ? ['remaining_attempts' => $result['remaining_attempts']] : [];
        $this->jsonResponse($result['success'], $result['message'], $result['data'] ?? null, $result['status'] ?? 200, $extra);
    }

    public function resetPasswordWithOtp(): void
    {
        $payload = $this->getJsonPayload();
        $requestToken = trim((string)($payload['request_token'] ?? ''));
        $resetToken = trim((string)($payload['reset_token'] ?? ''));
        $newPassword = (string)($payload['password'] ?? ($payload['newPassword'] ?? ''));
        $confirmPassword = (string)($payload['confirmPassword'] ?? ($payload['password_confirmation'] ?? ''));

        $result = $this->db->resetPasswordWithVerifiedOtp($requestToken, $resetToken, $newPassword, $confirmPassword);
        if ($result['success']) {
            unset($_SESSION['huit_auth_user'], $_SESSION['huit_auth_token']);
        }
        $this->jsonResponse($result['success'], $result['message'], null, $result['status'] ?? 200);
    }

    // ==================== 5. BẢNG XẾP HẠNG & LƯU VÁN GAME ====================
    public function leaderboard(): void
    {
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $rows = $this->db->getLeaderboard($keyword, 20);
        $this->jsonResponse(true, 'Tải bảng xếp hạng thành công.', $rows);
    }

    public function saveSession(): void
    {
        $payload = $this->getJsonPayload();
        if (!empty($_SESSION['huit_auth_user']['id'])) {
            $payload['user_id'] = (int)$_SESSION['huit_auth_user']['id'];
            $payload['username'] = (string)($_SESSION['huit_auth_user']['username'] ?? '');
        }
        // Truyền đúng 1 tham số $payload để hết lỗi "Too many arguments"
        $result = $this->db->recordGameSession($payload);
        $this->jsonResponse(true, 'Đã lưu ván đấu thành công.', $result);
    }

    public function resetScore(): void
    {
        $userId = !empty($_SESSION['huit_auth_user']['id']) ? (int)$_SESSION['huit_auth_user']['id'] : null;
        $username = (string)($_SESSION['huit_auth_user']['username'] ?? '');

        if ($userId && method_exists($this->db, 'resetUserHighScoreById')) {
            $this->db->resetUserHighScoreById($userId);
        } elseif ($username !== '' && method_exists($this->db, 'resetUserHighScore')) {
            $this->db->resetUserHighScore($username);
        } elseif ($userId) {
            $stmt = $this->db->getConnection()->prepare("UPDATE users SET high_score = 0 WHERE id = ?");
            $stmt->execute([$userId]);
        }

        $this->jsonResponse(true, 'Đã đặt lại điểm cao nhất về 0.');
    }

    public function searchLevels(): void
    {
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $difficulty = trim((string)($_GET['difficulty'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = $this->db->getPaginatedLevels($keyword, $difficulty, $page, 6);
        $this->jsonResponse(true, 'Tìm kiếm thành công.', $data);
    }
}