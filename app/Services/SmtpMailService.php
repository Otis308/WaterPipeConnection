<?php

namespace App\Services;

class SmtpMailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->host = trim((string)(getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? 'smtp.gmail.com')));
        $this->port = (int)(getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587));
        $this->username = trim((string)(getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? '')));
        $this->password = trim((string)(getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? '')));
        $this->encryption = strtolower(trim((string)(getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls'))));
        $this->fromAddress = trim((string)(getenv('MAIL_FROM_ADDRESS') ?: ($_ENV['MAIL_FROM_ADDRESS'] ?? ($this->username ?: 'no-reply@huit.edu.vn'))));
        $this->fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'Water Pipe Connection')));
    }

    public function sendPasswordResetOtpEmail(string $toEmail, string $username, string $otpCode, int $expirySeconds = 60): array
    {
        $subject = "[Water Pipe Game] Mã xác thực OTP khôi phục mật khẩu ({$expirySeconds}s)";
        $htmlBody = $this->buildOtpHtmlTemplate($username, $otpCode, $expirySeconds);

        if ($this->host === '' || $this->username === '' || $this->password === '') {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: {$this->fromName} <{$this->fromAddress}>",
            ];
            $mailSent = @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
            if ($mailSent) {
                return ['sent' => true, 'error' => null];
            }

            return [
                'sent' => false,
                'error' => 'Dịch vụ gửi email chưa được cấu hình thông tin xác thực SMTP (MAIL_USERNAME / MAIL_PASSWORD trong file .env). Vui lòng cấu hình SMTP để nhận mã OTP.',
            ];
        }

        return $this->sendViaSmtpSocket($toEmail, $subject, $htmlBody);
    }

    private function sendViaSmtpSocket(string $toEmail, string $subject, string $htmlBody): array
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 10);

        if (!$socket) {
            return ['sent' => false, 'error' => 'Không thể kết nối đến máy chủ email SMTP. Vui lòng thử lại sau.'];
        }

        stream_set_timeout($socket, 10);

        try {
            $this->expectCode($socket, [220]);
            $this->sendCommand($socket, "EHLO localhost", [250]);

            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS", [220]);
                
                // Cấu hình ngữ cảnh SSL an toàn cho kết nối Gmail SMTP
                stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
                stream_context_set_option($socket, 'ssl', 'allow_self_signed', true);

                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return ['sent' => false, 'error' => 'Không thể thiết lập kết nối mã hóa TLS với Gmail SMTP.'];
                }
                $this->sendCommand($socket, "EHLO localhost", [250]);
            }

            $this->sendCommand($socket, "AUTH LOGIN", [334]);
            $this->sendCommand($socket, base64_encode($this->username), [334]);
            $this->sendCommand($socket, base64_encode($this->password), [235]);

            $this->sendCommand($socket, "MAIL FROM:<{$this->fromAddress}>", [250]);
            $this->sendCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
            $this->sendCommand($socket, "DATA", [354]);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';

            $headers = [
                "Date: " . date('r'),
                "From: {$encodedFromName} <{$this->fromAddress}>",
                "To: <{$toEmail}>",
                "Subject: {$encodedSubject}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit",
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
            $this->sendCommand($socket, $message, [250]);
            $this->sendCommand($socket, "QUIT", [221]);
            fclose($socket);

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $e) {
            @fclose($socket);
            return ['sent' => false, 'error' => 'Không thể gửi email xác thực lúc này. Vui lòng kiểm tra cấu hình SMTP.'];
        }
    }

    private function sendCommand(mixed $socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectCode($socket, $expectedCodes);
    }

    private function expectCode(mixed $socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException("SMTP Unexpected response code: {$code}");
        }
        return $response;
    }

    private function buildOtpHtmlTemplate(string $username, string $otpCode, int $expirySeconds): string
    {
        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');
        return "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;border:3px solid #0059ff;border-radius:12px;background:#f8fafc;color:#0f172a;'>
            <h2 style='color:#0059ff;margin-top:0;text-align:center;'>WATER PIPE CONNECTION</h2>
            <p>Xin chào <strong>{$safeUser}</strong>,</p>
            <p>Mã xác thực OTP (6 chữ số) để khôi phục mật khẩu của bạn là:</p>
            <div style='text-align:center;margin:20px 0;'>
                <span style='display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;color:#0059ff;background:#e0f2fe;padding:12px 24px;border-radius:10px;border:2px dashed #0059ff;'>
                    {$safeOtp}
                </span>
            </div>
            <p style='color:#dc2626;font-weight:bold;text-align:center;'>
                ⏱  Mã OTP này chỉ có hiệu lực trong vòng {$expirySeconds} giây và chỉ được sử dụng 1 lần duy nhất.
            </p>
        </div>";
    }
}