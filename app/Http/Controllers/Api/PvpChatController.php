<?php

namespace App\Http\Controllers\Api;

use App\Services\PdoDatabaseService;
use App\Services\PvpChatService;
use RuntimeException;
use Throwable;

class PvpChatController
{
    // Lazy DB: unauthenticated/invalid-CSRF requests never construct the shared PDO service.
    public function __construct(private ?PvpChatService $chat = null) {}

    public function handle(string $method): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $userId = (int)($_SESSION['huit_auth_user']['id'] ?? 0);
            if ($userId <= 0) throw new RuntimeException('Vui lòng đăng nhập để sử dụng chat.', 401);
            if (!in_array($method, ['GET', 'POST'], true)) throw new RuntimeException('Phương thức không hỗ trợ.', 405);
            if ($method === 'POST') {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
                if (!is_string($token) || empty($_SESSION['_token']) || !hash_equals($_SESSION['_token'], $token)) {
                    throw new RuntimeException('Phiên gửi tin đã hết hạn. Hãy tải lại trang.', 419);
                }
                $raw = file_get_contents('php://input', false, null, 0, 8193);
                $payload = strlen($raw) <= 8192 ? json_decode($raw, true) : null;
                if (!is_array($payload)) throw new RuntimeException('Dữ liệu tin nhắn không hợp lệ.', 422);
            } else {
                $payload = $_GET;
            }
            $matchId = $payload['match_id'] ?? '';
            if (!is_string($matchId) || !preg_match('/^[a-f0-9]{32}$/D', $matchId)) throw new RuntimeException('Trận không hợp lệ.', 422);
            $after = $payload['after_id'] ?? '0';
            if ($method === 'GET' && (!is_scalar($after) || !preg_match('/^\d{1,16}$/D', (string)$after))) {
                throw new RuntimeException('Cursor không hợp lệ.', 422);
            }
            // Do not hold PHP's session lock during polling or DB work.
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (!$this->chat) {
                $this->chat = new PvpChatService((new PdoDatabaseService())->getConnection());
                $this->chat->ensureSchema();
            }
            $data = $method === 'GET'
                ? $this->chat->listMessages($userId, $matchId, (int)$after)
                : ['message' => $this->chat->sendMessage($userId, $matchId, $payload['content'] ?? null, $payload['client_message_id'] ?? null)];
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $error) {
            $status = $error instanceof RuntimeException && in_array($error->getCode(), [401, 403, 405, 409, 419, 422, 429], true)
                ? $error->getCode() : 503;
            http_response_code($status);
            // PHP/Apache has no built-in reason phrase for Laravel's 419 status.
            if ($status === 419) header('HTTP/1.1 419 Page Expired');
            if ($status === 429) header('Retry-After: 10');
            if ($status === 405) header('Allow: GET, POST');
            if ($status === 503) error_log('PvP chat failed: ' . get_class($error));
            echo json_encode(['success' => false, 'message' => $status === 503 ? 'Chat tạm mất kết nối. Hãy thử lại.' : $error->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
