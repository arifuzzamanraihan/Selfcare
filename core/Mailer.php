<?php
// Selfcare original code. Sends plain text email through an SMTP server (PHP sockets only).
// Settings in .env: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD, SMTP_ENCRYPTION (ssl, tls or empty),
// MAIL_FROM_ADDRESS, MAIL_FROM_NAME. Without SMTP_HOST the PHP mail() function is used.
declare(strict_types=1);

namespace Selfcare;

final class Mailer
{
    public string $lastError = '';
    /** Settings that replace the .env mail settings for one vendor (smtp_host, smtp_port, smtp_user, smtp_password, smtp_enc, from_address, from_name). @var array<string,string> */
    public array $override = [];

    public function __construct(private Env $env)
    {
    }

    private function cfg(string $envKey, string $ovKey, string $default = ''): string
    {
        $v = (string) ($this->override[$ovKey] ?? '');
        return $v !== '' ? $v : $this->env->get($envKey, $default);
    }

    /** @param list<array{0:string,1:string,2:string}> $attach [file name, mime type, bytes] */
    public function send(string $to, string $subject, string $body, array $attach = [], ?string $html = null): bool
    {
        $this->lastError = '';
        $from = $this->cfg('MAIL_FROM_ADDRESS', 'from_address', 'no-reply@localhost');
        $name = $this->cfg('MAIL_FROM_NAME', 'from_name', 'Self Care');
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
" . ($attach ? '[attachments: ' . implode(', ', array_column($attach, 0)) . "]\n" : '') . "
", FILE_APPEND) !== false;
        }
        $host = $this->cfg('SMTP_HOST', 'smtp_host');
        if ($host === '') {
            [$h, $b] = $this->mime($body, $attach, $html);
            $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $b, "From: $name <$from>\r\n" . $h);
            if (!$ok) {
                $this->lastError = 'mail relay unavailable';
            }
            return $ok;
        }
        try {
            return $this->smtp($host, $to, $from, $name, $subject, $body, $attach, $html);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /** MIME headers and body. @param list<array{0:string,1:string,2:string}> $attach @return array{0:string,1:string} */
    private function mime(string $body, array $attach, ?string $html = null): array
    {
        $alt = null;
        if ($html !== null) {
            $ab = 'sa' . bin2hex(random_bytes(8));
            $alt = "--$ab\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body))
                . "--$ab\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$ab--\r\n";
            if (!$attach) {
                return ["MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$ab\"", $alt];
            }
        }
        if (!$attach) {
            return ["MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64", chunk_split(base64_encode($body))];
        }
        $b = 'sc' . bin2hex(random_bytes(8));
        $out = $alt !== null
            ? "--$b\r\nContent-Type: multipart/alternative; boundary=\"" . $ab . "\"\r\n\r\n" . $alt
            : "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
        foreach ($attach as [$fn, $mt, $bytes]) {
            $fn = preg_replace('/[^A-Za-z0-9._-]/', '_', $fn);
            $out .= "--$b\r\nContent-Type: $mt; name=\"$fn\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fn\"\r\n\r\n" . chunk_split(base64_encode($bytes));
        }
        return ["MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"", $out . "--$b--\r\n"];
    }

    /** @param list<array{0:string,1:string,2:string}> $attach */
    private function smtp(string $host, string $to, string $from, string $name, string $subject, string $body, array $attach = [], ?string $html = null): bool
    {
        $port = (int) $this->cfg('SMTP_PORT', 'smtp_port', '587');
        $enc = strtolower($this->cfg('SMTP_ENCRYPTION', 'smtp_enc'));
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
        $user = $this->cfg('SMTP_USER', 'smtp_user');
        if ($user !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode($user), '334');
            $cmd(base64_encode($this->cfg('SMTP_PASSWORD', 'smtp_password')), '235');
        }
        $cmd('MAIL FROM:<' . $from . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '250');
        $cmd('DATA', '354');
        [$mh, $mb] = $this->mime($body, $attach, $html);
        $msg = 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\n"
            . "To: <$to>\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@selfcare>' . "\r\n"
            . $mh . "\r\n\r\n" . $mb;
        fwrite($fp, $msg . "\r\n.\r\n");
        if (!str_starts_with($read(), '250')) {
            throw new \RuntimeException('message rejected');
        }
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
