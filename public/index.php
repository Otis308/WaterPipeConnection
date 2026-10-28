<?php
/**
 * Front Controller — Điểm vào duy nhất của ứng dụng Laravel 12 (public/index.php)
 * Xử lý định tuyến (Routing), Method Spoofing (@method('PUT'/'DELETE')), CSRF Verification (@csrf)
 * và điều phối tới các Controller/Action tương ứng.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\Api\GameApiController;
use App\Http\Controllers\Api\PvpApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameLevelController;
use App\Http\Controllers\GamePlayController;
use App\Http\Controllers\PdoLabController;
use App\Http\Controllers\PlayerProfileController;

// Hỗ trợ PHP built-in server (php -S localhost:8000 -t public) phục vụ file tĩnh trực tiếp
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile = __DIR__ . $urlPath;
    if ($urlPath !== '/' && $urlPath !== '/index.html' && $urlPath !== '/TrangGame.html' && is_file($staticFile)) {
        return false;
    }
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestUri = '/' . trim($requestUri, '/');
if ($requestUri !== '/') {
    $requestUri = rtrim($requestUri, '/');
}

// Xử lý Method Spoofing (@method('PUT') / @method('DELETE') - Bài 7 Slide 23)
$httpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($httpMethod === 'POST' && !empty($_POST['_method'])) {
    $httpMethod = strtoupper(trim($_POST['_method']));
}

// Kiểm tra bảo mật CSRF Token (@csrf) cho các form Web POST/PUT/DELETE (Bài 7 Slide 22 & Bài 9 Slide 28)
if (in_array($httpMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !str_starts_with($requestUri, '/api/')) {
    $submittedToken = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($submittedToken) || $submittedToken === '' || !hash_equals(csrf_token(), $submittedToken)) {
        abort(419, '419 | Page Expired — Thiếu hoặc sai CSRF Token (@csrf).');
    }
}

// ==================== ĐỊNH TUYẾN API (routes/api.php) ====================
if (str_starts_with($requestUri, '/api/')) {
    // Pure scoring endpoint for the browser-generated solo map; never opens a DB.
    if ($requestUri === '/api/game/scoring-baseline' && $httpMethod === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        $map = strlen($raw) <= 65536 ? json_decode($raw, true) : null;
        if (!is_array($map)) {
            http_response_code(422);
            echo json_encode(['success' => false]);
        } else {
            $recheck = !empty($map['_recheck']);
            unset($map['_scoring'], $map['_recheck']);
            echo json_encode(['success' => true, 'data' => App\Services\PipeScoringService::baseline($map, $recheck)]);
        }
        exit;
    }
    // Session-only chat must bypass PvpApiController's legacy user_id fallback.
    if ($requestUri === '/api/pvp/chat') {
        (new App\Http\Controllers\Api\PvpChatController())->handle($httpMethod);
        exit;
    }
    // Include constructor/DDL failures in the matchmaking JSON boundary.
    if (in_array($requestUri, ['/api/pvp/matchmaking/join', '/api/pvp/matchmaking/cancel'], true) && $httpMethod === 'POST') {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        header('Cache-Control: no-store');
        try {
            $pvpApi = new PvpApiController();
            if ($requestUri === '/api/pvp/matchmaking/join') {
                $pvpApi->joinMatchmaking();
            } else {
                $pvpApi->cancelMatchmaking();
            }
        } catch (\Throwable $e) {
            error_log(sprintf('[PvP matchmaking] %s: %s (code %s) at %s:%d',
                $requestUri, $e->getMessage(), $e->getCode(), $e->getFile(), $e->getLine()));
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['success' => false, 'message' => 'Máy chủ không thể xử lý tìm trận. Vui lòng thử lại sau.', 'data' => null], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    // 1. Tuyến PvP Real-time, Matchmaking & Phòng đấu
    if (str_starts_with($requestUri, '/api/pvp/')) {
        $pvpApi = new PvpApiController();
        match (true) {
            $requestUri === '/api/pvp/create-room' && $httpMethod === 'POST' => $pvpApi->createRoom(),
            $requestUri === '/api/pvp/join-room' && $httpMethod === 'POST' => $pvpApi->joinRoom(),
            $requestUri === '/api/pvp/leave-room' && $httpMethod === 'POST' => $pvpApi->leaveRoom(),
            $requestUri === '/api/pvp/toggle-ready' && $httpMethod === 'POST' => $pvpApi->toggleReady(),
            $requestUri === '/api/pvp/update-config' && $httpMethod === 'POST' => $pvpApi->updateConfig(),
            $requestUri === '/api/pvp/start-match' && $httpMethod === 'POST' => $pvpApi->startMatch(),
            $requestUri === '/api/pvp/room-status' && $httpMethod === 'GET' => $pvpApi->getRoomStatus(),
            $requestUri === '/api/pvp/match-status' && $httpMethod === 'GET' => $pvpApi->getMatchStatus(),
            $requestUri === '/api/pvp/match-results' && $httpMethod === 'GET' => $pvpApi->getMatchResults(),
            $requestUri === '/api/pvp/submit-move' && $httpMethod === 'POST' => $pvpApi->submitMove(),
            $requestUri === '/api/pvp/submit-solution' && $httpMethod === 'POST' => $pvpApi->submitSolution(),
            $requestUri === '/api/pvp/reconnect' && $httpMethod === 'GET' => $pvpApi->reconnect(),
            $requestUri === '/api/pvp/leaderboard' && $httpMethod === 'GET' => $pvpApi->getRankedLeaderboard(),
            $requestUri === '/api/pvp/broadcasting/auth' && $httpMethod === 'POST' => $pvpApi->broadcastingAuth(),
            default => (function () {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'PVP API Endpoint not found']);
                exit;
            })(),
        };
    }

    // 2. Tuyến Admin API (Quản trị viên)
    if (str_starts_with($requestUri, '/api/admin/')) {
        if (preg_match('#^/api/admin/users/(\d+)/pvp-history$#', $requestUri, $m) && $httpMethod === 'GET') {
            $db = new \App\Services\PdoDatabaseService();
            $history = $db->getUserPvpMatchHistory((int)$m[1], 20);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'data' => $history], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($requestUri === '/api/admin/test-map-seed' && $httpMethod === 'POST') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $mapSize = $data['map_size'] ?? '3x8';
            $difficulty = $data['difficulty'] ?? 'easy';
            $numPairs = max(1, min(4, (int)($data['num_pairs'] ?? 2)));
            $seed = trim((string)($data['seed'] ?? ''));
            if ($seed === '') {
                $seed = 'TEST_' . bin2hex(random_bytes(4));
            }

            try {
                $mapData = \App\Services\PvpMapService::generateMap($mapSize, $difficulty, $numPairs, $seed);
                $configHash = \App\Services\PvpMapService::calculateConfigHash([
                    'map_size' => $mapSize,
                    'difficulty' => $difficulty,
                    'num_pairs' => $numPairs,
                    'map_seed' => $seed,
                ]);
                $mapData['configHash'] = $configHash;

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Bản đồ sinh thuật toán Winding DFS thành công!',
                    'data' => $mapData
                ], JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin API Endpoint not found']);
        exit;
    }

    $api = new GameApiController();
    match (true) {
        $requestUri === '/api/leaderboard' && $httpMethod === 'GET' => $api->leaderboard(),
        $requestUri === '/api/levels/search' && $httpMethod === 'GET' => $api->searchLevels(),

        // Đăng nhập & Đăng ký (chấp nhận cả /api/auth/login và /api/login)
        ($requestUri === '/api/auth/login' || $requestUri === '/api/login') && $httpMethod === 'POST' => $api->login(),
        ($requestUri === '/api/auth/register' || $requestUri === '/api/register') && $httpMethod === 'POST' => $api->register(),
        ($requestUri === '/api/auth/logout' || $requestUri === '/api/logout') && $httpMethod === 'POST' => $api->logout(),
        ($requestUri === '/api/auth/me' || $requestUri === '/api/me') && $httpMethod === 'GET' => $api->me(),

        // Quên mật khẩu OTP (chấp nhận cả URL ngắn /api/auth/send-otp lẫn URL dài)
        ($requestUri === '/api/auth/forgot-password/send-otp' || $requestUri === '/api/auth/send-otp' || $requestUri === '/api/send-otp') && $httpMethod === 'POST' => $api->sendForgotPasswordOtp(),
        ($requestUri === '/api/auth/forgot-password/resend-otp' || $requestUri === '/api/auth/resend-otp' || $requestUri === '/api/resend-otp') && $httpMethod === 'POST' => $api->sendForgotPasswordOtp(),
        ($requestUri === '/api/auth/forgot-password/verify-otp' || $requestUri === '/api/auth/verify-otp' || $requestUri === '/api/verify-otp') && $httpMethod === 'POST' => $api->verifyForgotPasswordOtp(),
        ($requestUri === '/api/auth/forgot-password/reset-password' || $requestUri === '/api/auth/reset-password' || $requestUri === '/api/reset-password') && $httpMethod === 'POST' => $api->resetPasswordWithOtp(),

        $requestUri === '/api/game/save-session' && $httpMethod === 'POST' => $api->saveSession(),
        $requestUri === '/api/game/reset-score' && $httpMethod === 'POST' => $api->resetScore(),
        default => (function () {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'API Endpoint not found']);
            exit;
        })(),
    };
}

// ==================== ĐỊNH TUYẾN WEB (routes/web.php) ====================

if ($requestUri === '/profile' && $httpMethod === 'GET') {
    (new PlayerProfileController())->show();
}
if ($requestUri === '/profile' && $httpMethod === 'POST') {
    (new PlayerProfileController())->update();
}

// 1. Giao diện Game Nối Ống Nước gốc (Giữ nguyên 100% UI/UX)
if (($requestUri === '/' || $requestUri === '/index.html' || $requestUri === '/game/home') && $httpMethod === 'GET') {
    (new GamePlayController())->home();
}
if (($requestUri === '/TrangGame.html' || $requestUri === '/game/play') && $httpMethod === 'GET') {
    (new GamePlayController())->play();
}

// 2. Cổng Quản trị Admin, OOP Lab & PDO SQL Lab - Chỉ Admin được truy cập
if ($requestUri === '/admin' && $httpMethod === 'GET') {
    // Check if user is admin
    $user = $_SESSION['huit_auth_user'] ?? null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Bạn không có quyền truy cập trang Quản trị. Chỉ tài khoản Admin mới được phép.';
        header('Location: /');
        exit;
    }
    (new GamePlayController())->portal();
}
if ($requestUri === '/oop-lab' && $httpMethod === 'GET') {
    // Check if user is admin
    $user = $_SESSION['huit_auth_user'] ?? null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Bạn không có quyền truy cập trang OOP Lab. Chỉ tài khoản Admin mới được phép.';
        header('Location: /');
        exit;
    }
    (new GamePlayController())->oopLab();
}
if ($requestUri === '/pdo-lab' && $httpMethod === 'GET') {
    // Check if user is admin
    $user = $_SESSION['huit_auth_user'] ?? null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Bạn không có quyền truy cập trang PDO Lab. Chỉ tài khoản Admin mới được phép.';
        header('Location: /');
        exit;
    }
    (new PdoLabController())->index();
}

// 3. Xác thực
if ($requestUri === '/login' && $httpMethod === 'GET') {
    (new AuthController())->showLogin();
}
if ($requestUri === '/login' && $httpMethod === 'POST') {
    (new AuthController())->login();
}
if ($requestUri === '/register' && $httpMethod === 'POST') {
    (new AuthController())->register();
}
if ($requestUri === '/logout' && $httpMethod === 'POST') {
    (new AuthController())->logout();
}

// 4. Admin user management - Chỉ Admin được truy cập
if (preg_match('#^/admin/users/(\d+)/role$#', $requestUri, $m) && $httpMethod === 'POST') {
    $user = $_SESSION['huit_auth_user'] ?? null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Bạn không có quyền thực hiện hành động này. Chỉ tài khoản Admin mới được phép.';
        header('Location: /');
        exit;
    }
    (new AuthController())->updateRole((int)$m[1]);
}
if (preg_match('#^/admin/users/(\d+)/toggle-active$#', $requestUri, $m) && $httpMethod === 'POST') {
    $user = $_SESSION['huit_auth_user'] ?? null;
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Bạn không có quyền thực hiện hành động này. Chỉ tài khoản Admin mới được phép.';
        header('Location: /');
        exit;
    }
    (new AuthController())->toggleActive((int)$m[1]);
}

// 4. Routing nâng cao: whereNumber('page') & Regex slug (Bài 7 Slide 4-5)
if (preg_match('#^/levels/page/(\d+)$#', $requestUri, $m) && $httpMethod === 'GET') {
    $_GET['page'] = (int)$m[1];
    (new GameLevelController())->index();
}
if (preg_match('#^/levels/slug(?:/([a-z0-9-]+))?$#', $requestUri, $m) && $httpMethod === 'GET') {
    $slug = $m[1] ?? 'khoi-nguon-dong-chay-tan-phu';
    $_GET['keyword'] = str_replace('-', ' ', $slug);
    (new GameLevelController())->index();
}

// 5. Resource Controller 7 Actions cho GameLevel (Bài 7 Slide 8-12)
if ($requestUri === '/levels' && $httpMethod === 'GET') {
    (new GameLevelController())->index();
}
if ($requestUri === '/levels/create' && $httpMethod === 'GET') {
    (new GameLevelController())->create();
}
if ($requestUri === '/levels' && $httpMethod === 'POST') {
    (new GameLevelController())->store();
}
if (preg_match('#^/levels/(\d+)$#', $requestUri, $m) && $httpMethod === 'GET') {
    (new GameLevelController())->show((int)$m[1]);
}
if (preg_match('#^/levels/(\d+)/edit$#', $requestUri, $m) && $httpMethod === 'GET') {
    (new GameLevelController())->edit((int)$m[1]);
}
if (preg_match('#^/levels/(\d+)$#', $requestUri, $m) && in_array($httpMethod, ['PUT', 'PATCH'], true)) {
    (new GameLevelController())->update((int)$m[1]);
}
if (preg_match('#^/levels/(\d+)$#', $requestUri, $m) && $httpMethod === 'DELETE') {
    (new GameLevelController())->destroy((int)$m[1]);
}

abort(404, "Không tìm thấy đường dẫn [{$requestUri}] (Kiểm tra lại URL hoặc định dạng tham số Regex).");
