<?php
// Selfcare original code. Sends plain text email through an SMTP server (PHP sockets only).
// Settings in .env: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, SMTP_ENCRYPTION (ssl, tls or empty),
// MAIL_FROM_ADDRESS, MAIL_FROM_NAME. Without SMTP_HOST the PHP mail() function is used.
declare(strict_types=1);

namespace Selfcare;

final class Mailer
{
    public string $lastError = '';

    public function __construct(private Env $env)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $from = $this->env->get('MAIL_FROM_ADDRESS', 'no-reply@localhost');
        $name = $this->env->get('MAIL_FROM_NAME', 'Self Care');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'bad address';
            return false;
        }
        if ($this->env->get('MAIL_DRIVER') === 'log') { // development: write the message to storage/mail.log
            $dir = dirname(__DIR__) . '/storage';
            @mkdir($dir, 0775, true);
            return file_put_contents($dir . '/mail.log', '[' . date('c') . "] To: $to
Subject: $subject
$body

", FILE_APPEND) !== false;
        }
        $host = $this->env->get('SMTP_HOST');
        if ($host === '') {
            $headers = "From: $name <$from>\r\nContent-Type: text/plain; charset=UTF-8";
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }
        try {
            return $this->smtp($host, $to, $from, $name, $subject, $body);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function smtp(string $host, string $to, string $from, string $name, string $subject, string $body): bool
    {
        $port = (int) $this->env->get('SMTP_PORT', '587');
        $enc = strtolower($this->env->get('SMTP_ENCRYPTION'));
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException('connect failed');
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $all = '';
            while (($line = fgets($fp, 515)) !== false) {
                $all .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $all;
        };
        $cmd = function (string $c, string $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) {
                throw new \RuntimeException('smtp error ' . substr($r, 0, 3));
            }
            return $r;
        };
        if (!str_starts_with($read(), '220')) {
            throw new \RuntimeException('no greeting');
        }
        $ehlo = 'selfcare.local';
        $cmd('EHLO ' . $ehlo, '250');
        if ($enc === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('tls failed');
            }
            $cmd('EHLO ' . $ehlo, '250');
        }
        $user = $this->env->get('SMTP_USER');
        if ($user !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode($user), '334');
            $cmd(base64_encode($this->env->get('SMTP_PASSWORD')), '235');
        }
        $cmd('MAIL FROM:<' . $from . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');
        $msg = 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\n"
            . "To: <$to>\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@selfcare>' . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body));
        fwrite($fp, $msg . "\r\n.\r\n");
        if (!str_starts_with($read(), '250')) {
            throw new \RuntimeException('message rejected');
        }
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
