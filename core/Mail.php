<?php
// Selfcare original code. Professional email: branded HTML templates, a queue with retry, a delivery log,
// and signed expiring single use links (email verification, change of email).
declare(strict_types=1);

namespace Selfcare;

final class Mail
{
    /** Minutes to wait before try 2, 3, 4, 5. After the last try the message is marked dead. */
    private const BACKOFF = [5, 15, 60, 240];

    public function __construct(private App $app)
    {
    }

    /** Branded HTML for a message. $cta is [button label, address]. */
    public static function html(string $heading, string $bodyHtml, ?array $cta = null, ?string $vendor = null, string $note = ''): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $brand = '#0f766e';
        $btn = $cta === null ? '' : '<p style="margin:26px 0"><a href="' . $e($cta[1]) . '" style="background:' . $brand . ';color:#ffffff;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:600;display:inline-block">' . $e($cta[0]) . '</a></p>'
            . '<p style="font-size:12px;color:#64748b;margin:0 0 6px">If the button does not work, copy this address into your browser:</p><p style="font-size:12px;word-break:break-all;margin:0"><a href="' . $e($cta[1]) . '" style="color:' . $brand . '">' . $e($cta[1]) . '</a></p>';
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($heading) . '</title></head>'
            . '<body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#0f172a">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
            . '<tr><td style="background:' . $brand . ';padding:20px 28px;color:#ffffff"><div style="font-size:20px;font-weight:700;letter-spacing:.2px">Self Care</div>'
            . ($vendor !== null && $vendor !== '' ? '<div style="font-size:13px;opacity:.9;margin-top:2px">' . $e($vendor) . '</div>' : '') . '</td></tr>'
            . '<tr><td style="padding:28px"><h1 style="font-size:20px;margin:0 0 14px">' . $e($heading) . '</h1>'
            . '<div style="font-size:15px;line-height:1.6;color:#1e293b">' . $bodyHtml . '</div>' . $btn
            . ($note !== '' ? '<p style="font-size:13px;color:#64748b;margin:22px 0 0">' . $e($note) . '</p>' : '') . '</td></tr>'
            . '<tr><td style="background:#f8fafc;padding:16px 28px;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0">Sent by Self Care' . ($vendor ? ' on behalf of ' . $e($vendor) : '') . '. This is an automatic message, please do not reply. If you did not expect it, you can ignore it.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** Plain text twin of a message. */
    public static function text(string $heading, string $bodyText, ?array $cta = null, ?string $vendor = null, string $note = ''): string
    {
        return $heading . "\n\n" . $bodyText . ($cta ? "\n\n" . $cta[0] . ":\n" . $cta[1] : '') . ($note !== '' ? "\n\n" . $note : '') . "\n\nSelf Care" . ($vendor ? ' on behalf of ' . $vendor : '') . "\nThis is an automatic message. If you did not expect it, ignore it.";
    }

    /**
     * Queues a branded message and tries to send it now. A failure stays in the log for the retry job.
     * @param string $bodyText plain paragraphs; they are escaped and turned into HTML paragraphs
     * @return int queue id
     */
    public function send(string $kind, string $to, string $subject, string $heading, string $bodyText, ?array $cta = null, ?string $vendor = null, ?string $tenantId = null, ?int $userId = null, string $note = ''): int
    {
        $paras = '';
        foreach (preg_split('/\n{2,}/', trim($bodyText)) ?: [] as $p) {
            $paras .= '<p style="margin:0 0 12px">' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        $id = $this->app->db->insert('sc_mail_queue', [
            'tenant_id' => $tenantId, 'user_id' => $userId, 'kind' => $kind, 'to_email' => mb_substr($to, 0, 191), 'subject' => mb_substr($subject, 0, 255),
            'body_text' => self::text($heading, $bodyText, $cta, $vendor, $note), 'body_html' => self::html($heading, $paras, $cta, $vendor, $note),
            'status' => 'queued', 'tries' => 0, 'next_try_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->deliver($id);
        return $id;
    }

    /** Sends one queued message. @return bool sent */
    public function deliver(int $id): bool
    {
        $m = $this->app->db->one("SELECT * FROM sc_mail_queue WHERE id = ? AND status IN ('queued','failed')", [$id]);
        if ($m === null) {
            return false;
        }
        $ok = $this->app->mailer->send((string) $m['to_email'], (string) $m['subject'], (string) $m['body_text'], [], (string) $m['body_html']);
        $tries = (int) $m['tries'] + 1;
        if ($ok) {
            $this->app->db->run("UPDATE sc_mail_queue SET status = 'sent', tries = ?, sent_at = NOW(), last_error = NULL, next_try_at = NULL WHERE id = ?", [$tries, $id]);
            return true;
        }
        $err = mb_substr($this->app->mailer->lastError ?: 'could not send', 0, 255);
        if ($tries > count(self::BACKOFF)) {
            $this->app->db->run("UPDATE sc_mail_queue SET status = 'dead', tries = ?, last_error = ?, next_try_at = NULL WHERE id = ?", [$tries, $err, $id]);
        } else {
            $this->app->db->run("UPDATE sc_mail_queue SET status = 'failed', tries = ?, last_error = ?, next_try_at = ? WHERE id = ?", [$tries, $err, date('Y-m-d H:i:s', time() + 60 * self::BACKOFF[$tries - 1]), $id]);
        }
        return false;
    }

    /** Retry job: sends everything that is due. @return int sent */
    public function flush(int $limit = 50): int
    {
        $n = 0;
        foreach ($this->app->db->all("SELECT id FROM sc_mail_queue WHERE status IN ('queued','failed') AND (next_try_at IS NULL OR next_try_at <= NOW()) ORDER BY id LIMIT " . (int) $limit) as $r) {
            $n += $this->deliver((int) $r['id']) ? 1 : 0;
        }
        return $n;
    }

    /** Puts a dead or failed message back in the queue (super admin button). */
    public function retry(int $id): bool
    {
        $this->app->db->run("UPDATE sc_mail_queue SET status = 'failed', tries = 0, next_try_at = NOW() WHERE id = ? AND status IN ('failed','dead')", [$id]);
        return $this->deliver($id);
    }

    // ------------------------------------------------------------------ signed links
    /** New link token; older open tokens of the same purpose for the same person or request are closed. */
    public function token(string $purpose, ?int $userId, ?int $refId, string $email, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        $db = $this->app->db;
        $db->run('UPDATE sc_email_tokens SET used_at = NOW() WHERE purpose = ? AND used_at IS NULL AND ((? IS NOT NULL AND user_id = ?) OR (? IS NOT NULL AND ref_id = ?))', [$purpose, $userId, $userId, $refId, $refId]);
        $db->insert('sc_email_tokens', ['purpose' => $purpose, 'user_id' => $userId, 'ref_id' => $refId, 'email' => mb_substr(strtolower($email), 0, 191), 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds), 'ip' => $this->app->ip()]);
        return $token;
    }

    /** Looks a token up (does not use it). @return array<string,mixed>|null */
    public function peek(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->app->db->one('SELECT * FROM sc_email_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1', [hash('sha256', $token)]);
    }

    public function burn(int $tokenId): void
    {
        $this->app->db->run('UPDATE sc_email_tokens SET used_at = NOW() WHERE id = ?', [$tokenId]);
    }

    // ------------------------------------------------------------------ ready made messages
    /** @param array<string,mixed> $user a users row */
    private function vendorName(array $user): ?string
    {
        if (empty($user['tenant_id'])) {
            return null;
        }
        $v = $this->app->tenant->bySlug((string) $user['tenant_id']);
        return $v['name'] ?? ($user['hospital_name'] ?? null);
    }

    /** Link base for a person: their vendor address, or the main site. */
    public function linkFor(array $user, string $path): string
    {
        $slug = (string) ($user['tenant_id'] ?? '');
        return $slug !== '' && $this->app->tenant->bySlug($slug) !== null ? $this->app->tenant->siteUrl($slug, $path) : $this->app->tenant->mainUrl($path);
    }

    /** Asks a person to confirm their email address. Returns the queue id. */
    public function sendVerify(array $user, ?string $toEmail = null, string $purpose = 'verify'): int
    {
        $email = $toEmail ?? (string) $user['email'];
        $token = $this->token($purpose, (int) $user['id'], null, $email, 2 * 86400);
        $vendor = $this->vendorName($user);
        return $this->send($purpose === 'change' ? 'email_change' : 'verify', $email, $purpose === 'change' ? 'Confirm your new email address' : 'Confirm your email address',
            $purpose === 'change' ? 'Confirm your new email' : 'Confirm your email',
            'Hello ' . $user['first_name'] . ",\n\n" . ($purpose === 'change' ? 'You asked to use this address to sign in to Self Care. Please confirm it.' : 'Please confirm that this is your email address, so we can reach you about your account.') . "\n\nThe link works once and expires in 48 hours.",
            ['Confirm email', $this->linkFor($user, 'verify') . '?token=' . $token], $vendor, (string) ($user['tenant_id'] ?? '') ?: null, (int) $user['id'], 'If you did not ask for this, ignore this message. Nothing will change.');
    }

    /** A set your password link for a new person invited by an admin. Returns the link. */
    public function sendInvite(array $user, string $invitedBy = '', bool $deliver = true): string
    {
        $token = bin2hex(random_bytes(32));
        $db = $this->app->db;
        $db->run('UPDATE sc_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
        $db->insert('sc_password_resets', ['user_id' => (int) $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400)]);
        $link = $this->linkFor($user, 'reset') . '?token=' . $token . '&welcome=1';
        $vendor = $this->vendorName($user);
        if (!$deliver) {
            return $link;
        }
        $this->send('invite', (string) $user['email'], 'Welcome to ' . ($vendor ?: 'Self Care') . ': set your password', 'Welcome, ' . $user['first_name'],
            ($invitedBy !== '' ? $invitedBy . ' has created an account for you' : 'An account was created for you') . ($vendor ? ' at ' . $vendor : '') . ".\n\nChoose your own password with the button below. Nobody else will ever know it. The link works once and expires in 7 days.\n\nYour sign in email is " . $user['email'] . '.',
            ['Set my password', $link], $vendor, (string) ($user['tenant_id'] ?? '') ?: null, (int) $user['id']);
        return $link;
    }

    public function sendPasswordChanged(array $user, string $how = 'changed'): void
    {
        $this->send('password_changed', (string) $user['email'], 'Your Self Care password was ' . $how, 'Your password was ' . $how,
            'Hello ' . $user['first_name'] . ",\n\nThe password of your account was " . $how . ' on ' . date('d M Y, H:i') . ".\n\nIf this was you, there is nothing to do. If it was not you, reset your password now and tell your administrator.",
            ['Reset my password', $this->linkFor($user, 'forgot')], $this->vendorName($user), (string) ($user['tenant_id'] ?? '') ?: null, (int) $user['id']);
    }

    public function sendEmailChangedNotice(array $user, string $newEmail): void
    {
        $this->send('email_changed', (string) $user['email'], 'The sign in email of your account is changing', 'Your sign in email is changing',
            'Hello ' . $user['first_name'] . ",\n\nA change of the sign in email of your account to " . $newEmail . " was requested. Until the new address is confirmed, you keep signing in with this address.\n\nIf this was not you, change your password now.",
            null, $this->vendorName($user), (string) ($user['tenant_id'] ?? '') ?: null, (int) $user['id']);
    }
}
