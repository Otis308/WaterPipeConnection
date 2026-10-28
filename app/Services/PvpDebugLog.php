<?php

namespace App\Services;

/**
 * Ghi log debug NDJSON cho phiên nghiệm thu (không chứa secret).
 */
class PvpDebugLog
{
    public static function write(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $payload = json_encode([
            'sessionId'    => '5823a4',
            'hypothesisId' => $hypothesisId,
            'location'     => $location,
            'message'      => $message,
            'data'         => $data,
            'timestamp'    => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'debug-5823a4.log';
        @file_put_contents($path, $payload . "\n", FILE_APPEND);
    }
}
