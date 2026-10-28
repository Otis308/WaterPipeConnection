<?php
/**
 * FILE: bootstrap/huit_hybrid_kernel.php
 * Đã fix triệt để lỗi: Blade Render Error: syntax error, unexpected token ":"
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if (getenv($k) === false) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

if (empty($_SESSION['_token'])) {
    $_SESSION['_token'] = bin2hex(random_bytes(16));
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string { return $_SESSION['_token'] ?? ''; }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
if (!function_exists('method_field')) {
    function method_field(string $method): string {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars(strtoupper($method), ENT_QUOTES, 'UTF-8') . '">';
    }
}
if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}
if (!function_exists('url')) {
    function url(string $path = '/'): string { return '/' . ltrim($path, '/'); }
}
if (!function_exists('asset')) {
    function asset(string $path): string { return '/' . ltrim($path, '/'); }
}
if (!function_exists('route')) {
    function route(string $name, mixed $params = []): string {
        $map = [
            'game.home' => '/',
            'game.play' => '/TrangGame.html',
            'admin.dashboard' => '/admin',
            'portal.dashboard' => '/admin',
            'oop.lab' => '/oop-lab',
            'pdo.lab' => '/pdo-lab',
            'levels.index' => '/levels',
            'levels.create' => '/levels/create',
            'levels.store' => '/levels',
            'levels.show' => '/levels/{id}',
            'levels.edit' => '/levels/{id}/edit',
            'levels.update' => '/levels/{id}',
            'levels.destroy' => '/levels/{id}',
            'levels.page' => '/levels/page/{page}',
            'levels.slug' => '/levels/slug/{slug}',
            'login' => '/login',
            'login.submit' => '/login',
            'register.submit' => '/register',
            'logout' => '/logout',
            'admin.users.role' => '/admin/users/{id}/role',
            'admin.users.toggle_active' => '/admin/users/{id}/toggle-active',
        ];
        $uri = $map[$name] ?? ('/' . str_replace('.', '/', $name));
        if (!is_array($params)) {
            $params = ['id' => $params, 'page' => $params, 'slug' => $params, 'username' => $params];
        }
        foreach ($params as $k => $v) {
            $uri = str_replace('{' . $k . '}', urlencode((string)$v), $uri);
        }
        return $uri;
    }
}
class HuitRedirectResponse
{
    private string $targetUrl;
    public function __construct(string $targetUrl) {
        $this->targetUrl = $targetUrl;
    }
    public function with(string $key, mixed $value): self {
        $_SESSION[$key] = $value;
        return $this;
    }
    public function send(): void {
        if (!headers_sent()) {
            header("Location: {$this->targetUrl}");
            exit;
        }
    }
    public function __destruct() {
        $this->send();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to = '/'): HuitRedirectResponse {
        return new HuitRedirectResponse($to);
    }
}

if (!function_exists('back')) {
    function back(): HuitRedirectResponse {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '/';
        return new HuitRedirectResponse($referrer);
    }
}
if (!function_exists('abort')) {
    function abort(int $code = 404, string $message = 'Not Found'): void {
        http_response_code($code);
        echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>HTTP {$code}</title>";
        echo "<link rel='stylesheet' href='/Bootstrap/CSS/bootstrap.min.css'></head>";
        echo "<body class='bg-dark text-light d-flex align-items-center justify-content-center' style='min-height:100vh;'>";
        echo "<div class='card bg-secondary text-white p-4 shadow-lg' style='max-width:600px;'>";
        echo "<h2 class='text-warning'>HTTP {$code}</h2><p class='fs-5'>" . htmlspecialchars($message) . "</p>";
        echo "<div class='mt-3'><a href='/admin' class='btn btn-info me-2'>Về Trang Admin</a><a href='/' class='btn btn-light'>Về Trang Chủ Game</a></div>";
        echo "</div></body></html>";
        exit;
    }
}

class HuitBladeEngine
{
    private static array $sections = [];
    private static array $sectionStack = [];
    private static array $pushes = [];
    private static array $pushStack = [];

    public static function render(string $viewName, array $data = []): string
    {
        $viewsDir = dirname(__DIR__) . '/resources/views';
        $filePath = $viewsDir . '/' . str_replace('.', '/', $viewName) . '.blade.php';
        if (!file_exists($filePath)) {
            return "View [{$viewName}] not found at {$filePath}";
        }

        $errorsBag = $_SESSION['_validation_errors'] ?? [];
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        $data['errors'] = $errorsBag;
        $data['currentUser'] = $data['currentUser'] ?? $currentUser;

        $raw = file_get_contents($filePath);

        $parentLayout = null;
        if (preg_match('/@extends\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $raw, $m)) {
            $parentLayout = $m[1];
            $raw = str_replace($m[0], '', $raw);
        }

        $compiled = self::compileString($raw);
        $childOutput = self::evaluatePhp($compiled, $data);

        if ($parentLayout) {
            return self::render($parentLayout, $data);
        }

        return $childOutput;
    }

    public static function startSection(string $name, ?string $inlineContent = null): void
    {
        if ($inlineContent !== null) {
            self::$sections[$name] = $inlineContent;
            return;
        }
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $last = array_pop(self::$sectionStack);
        if ($last !== null) {
            self::$sections[$last] = ob_get_clean();
        }
    }

    public static function yieldContent(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function startPush(string $name): void
    {
        self::$pushStack[] = $name;
        ob_start();
    }

    public static function endPush(): void
    {
        $last = array_pop(self::$pushStack);
        if ($last !== null) {
            self::$pushes[$last][] = ob_get_clean();
        }
    }

    public static function yieldStack(string $name): string
    {
        return implode("\n", self::$pushes[$name] ?? []);
    }

    public static function renderComponent(string $name, array $attrs = [], string $slot = ''): string
    {
        $viewsDir = dirname(__DIR__) . '/resources/views/components';
        $compPath = $viewsDir . '/' . $name . '.blade.php';
        if (!file_exists($compPath)) {
            return $slot;
        }
        $raw = file_get_contents($compPath);
        $defaults = [];
        if (preg_match('/@props\(\s*(\[.*?\])\s*\)/s', $raw, $m)) {
            $raw = str_replace($m[0], '', $raw);
            $evaluatedProps = eval('return ' . $m[1] . ';');
            if (is_array($evaluatedProps)) {
                foreach ($evaluatedProps as $k => $v) {
                    if (is_int($k)) {
                        $defaults[$v] = null;
                    } else {
                        $defaults[$k] = $v;
                    }
                }
            }
        }
        $compData = array_merge($defaults, $attrs, ['slot' => $slot]);
        $compiled = self::compileString($raw);
        return self::evaluatePhp($compiled, $compData);
    }

    private static function compileString(string $blade): string
    {
        // Xóa comment Blade {{-- ... --}}
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

        // Biên dịch Blade Component có thẻ đóng <x-name ...>...</x-name>
        $blade = preg_replace_callback(
            '/<x-([a-zA-Z0-9_-]+)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/x-\1>/s',
            function ($m) {
                $compName = $m[1];
                $attrStr = self::parseComponentAttrs($m[2]);
                $innerCompiled = self::compileString($m[3]);
                return "<?php ob_start(); ?>{$innerCompiled}<?php echo \\HuitBladeEngine::renderComponent('{$compName}', {$attrStr}, ob_get_clean()); ?>";
            },
            $blade
        );

        // Biên dịch Blade Component tự đóng <x-name ... />
        $blade = preg_replace_callback(
            '/<x-([a-zA-Z0-9_-]+)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)\/>/s',
            function ($m) {
                $compName = $m[1];
                $attrStr = self::parseComponentAttrs($m[2]);
                return "<?php echo \\HuitBladeEngine::renderComponent('{$compName}', {$attrStr}, ''); ?>";
            },
            $blade
        );

        // Biểu thức chính quy khớp ngoặc đơn lồng nhau tới 3 cấp
        $balParen = '((?:[^()]+|\((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*\))*)';

        $blade = preg_replace(
            '/@include\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php echo \\HuitBladeEngine::render("$1", get_defined_vars()); ?>',
            $blade
        );

        $blade = preg_replace(
            '/@section\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(.+?)\s*\)/',
            '<?php \\HuitBladeEngine::startSection("$1", $2); ?>',
            $blade
        );

        $blade = preg_replace(
            '/@section\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php \\HuitBladeEngine::startSection("$1"); ?>',
            $blade
        );
        $blade = str_replace('@endsection', '<?php \\HuitBladeEngine::endSection(); ?>', $blade);

        $blade = preg_replace(
            '/@yield\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(.+?)\s*\)/',
            '<?php echo \\HuitBladeEngine::yieldContent("$1", $2); ?>',
            $blade
        );
        $blade = preg_replace(
            '/@yield\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php echo \\HuitBladeEngine::yieldContent("$1"); ?>',
            $blade
        );

        $blade = preg_replace(
            '/@stack\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php echo \\HuitBladeEngine::yieldStack("$1"); ?>',
            $blade
        );
        $blade = preg_replace(
            '/@push\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php \\HuitBladeEngine::startPush("$1"); ?>',
            $blade
        );
        $blade = str_replace('@endpush', '<?php \\HuitBladeEngine::endPush(); ?>', $blade);

        $blade = str_replace('@csrf', '<?php echo csrf_field(); ?>', $blade);
        $blade = preg_replace(
            '/@method\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php echo method_field("$1"); ?>',
            $blade
        );

        $blade = preg_replace(
            '/@error\(\s*([^\)]+)\s*\)/',
            '<?php if (!empty($errors[$1])): $message = $errors[$1]; ?>',
            $blade
        );
        $blade = str_replace('@enderror', '<?php endif; ?>', $blade);

        $blade = str_replace('@auth', '<?php if (!empty($_SESSION["huit_auth_user"])): ?>', $blade);
        $blade = str_replace('@endauth', '<?php endif; ?>', $blade);
        $blade = str_replace('@guest', '<?php if (empty($_SESSION["huit_auth_user"])): ?>', $blade);
        $blade = str_replace('@endguest', '<?php endif; ?>', $blade);

        // Fix @selected(...) có chứa hàm old() bên trong
        $blade = preg_replace(
            '/@selected\(' . $balParen . '\)/',
            '<?php echo ($1) ? "selected" : ""; ?>',
            $blade
        );

        $blade = preg_replace_callback(
            '/@class\((\[.*?\])\)/s',
            function ($m) {
                return 'class="<?php $__cls = []; foreach (' . $m[1] . ' as $__k => $__v) { if (is_int($__k)) { $__cls[] = $__v; } elseif ($__v) { $__cls[] = $__k; } } echo htmlspecialchars(implode(" ", $__cls)); ?>"';
            },
            $blade
        );

        $blade = str_replace('@php', '<?php ', $blade);
        $blade = str_replace('@endphp', ' ?>', $blade);

        // Fix @forelse và @foreach có ngoặc lồng nhau
        $blade = preg_replace_callback(
            '/@forelse\s*\(' . $balParen . '\)/',
            function ($m) {
                if (preg_match('/^(.*)\s+as\s+(.*)$/s', trim($m[1]), $parts)) {
                    $arr = trim($parts[1]);
                    $item = trim($parts[2]);
                    return "<?php if (!empty({$arr})): \$__idx = 0; foreach ({$arr} as {$item}): \$__idx++; \$loop = (object)['iteration' => \$__idx, 'index' => \$__idx - 1]; ?>";
                }
                return $m[0];
            },
            $blade
        );
        $blade = str_replace('@empty', '<?php endforeach; else: ?>', $blade);
        $blade = str_replace('@endforelse', '<?php endif; ?>', $blade);

        $blade = preg_replace_callback(
            '/@foreach\s*\(' . $balParen . '\)/',
            function ($m) {
                if (preg_match('/^(.*)\s+as\s+(.*)$/s', trim($m[1]), $parts)) {
                    $arr = trim($parts[1]);
                    $item = trim($parts[2]);
                    return "<?php \$__idx = 0; foreach ({$arr} as {$item}): \$__idx++; \$loop = (object)['iteration' => \$__idx, 'index' => \$__idx - 1]; ?>";
                }
                return $m[0];
            },
            $blade
        );
        $blade = str_replace('@endforeach', '<?php endforeach; ?>', $blade);

        // Fix @if và @elseif có ngoặc lồng nhau như @if(!empty($_SESSION['...']))
        $blade = preg_replace('/@if\s*\(' . $balParen . '\)/', '<?php if ($1): ?>', $blade);
        $blade = preg_replace('/@elseif\s*\(' . $balParen . '\)/', '<?php elseif ($1): ?>', $blade);
        $blade = str_replace('@else', '<?php else: ?>', $blade);
        $blade = str_replace('@endif', '<?php endif; ?>', $blade);

        // Raw output {!! ... !!}
        $blade = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $blade);

        // Bọc thêm ngoặc đơn (($1)) trước ?? "" để toán tử 3 ngôi (? :) không bao giờ lỗi cú pháp PHP 8+
        $blade = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?php echo htmlspecialchars((string)((($1)) ?? ""), ENT_QUOTES, "UTF-8"); ?>',
            $blade
        );

        return $blade;
    }

    private static function parseComponentAttrs(string $attrString): string
    {
        $pairs = [];
        // Tách riêng chuỗi ngoặc kép "..." và ngoặc đơn '...' để giữ nguyên biến mảng như :difficulty="$s['difficulty']"
        if (preg_match_all('/(:?[a-zA-Z0-9_-]+)=(?:"([^"]*)"|\'([^\']*)\')/', $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $val = ($match[2] !== '') ? $match[2] : ($match[3] ?? '');
                if (str_starts_with($key, ':')) {
                    $cleanKey = substr($key, 1);
                    $pairs[] = "'{$cleanKey}' => {$val}";
                } else {
                    $escapedVal = addslashes($val);
                    $pairs[] = "'{$key}' => '{$escapedVal}'";
                }
            }
        }
        return '[' . implode(', ', $pairs) . ']';
    }

    private static function evaluatePhp(string $__compiledPhp, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            eval('?>' . $__compiledPhp);
        } catch (\Throwable $e) {
            echo "<div class='alert alert-danger'>Blade Render Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        return ob_get_clean();
    }
}

if (!function_exists('view')) {
    function view(string $viewName, array $data = []): void
    {
        $_SESSION['_validation_errors'] = $_SESSION['validation_errors'] ?? [];
        $_SESSION['_old_input'] = $_SESSION['old_input'] ?? [];
        unset($_SESSION['validation_errors'], $_SESSION['old_input']);

        echo HuitBladeEngine::render($viewName, $data);
        exit;
    }
}
