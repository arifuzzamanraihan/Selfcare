<?php
// Selfcare original code. Holds the services for one request and the request context
// (which host, which vendor, who is signed in).
declare(strict_types=1);

namespace Selfcare;

final class App
{
    public static self $i;

    public Env $env;
    public Database $db;
    public Auth $auth;
    public View $view;
    public Router $router;
    public Tenant $tenant;
    public Site $site;
    public Plans $plans;
    public Access $access;
    public Throttle $throttle;
    public Mailer $mailer;
    public Mail $mail;
    /** helper closures shared by the lab route files */
    public array $lab = [];
    public Domains $domains;

    /** null = main website, '' = bad sub domain, otherwise the vendor slug from the Host header. */
    public ?string $hostSlug = null;
    /** Vendor row when the request is on a vendor host. @var array<string,mixed>|null */
    public ?array $hostVendor = null;
    /** Vendor website handlers, set by routes/site.php. */
    public ?\Closure $siteHome = null;
    public ?\Closure $siteContact = null;
    /** Renders a dashboard page: ($portalOrNull, $active, $title, $html, $options). Set in routes/portal.php. */
    public ?\Closure $dash = null;
    public ?\Closure $adminPage = null;
    /** Prescription editor markup, set by core/routes/rx.php and reused by the doctor workspace. */
    public ?\Closure $rxForm = null;
    /** Prescription print model, set by core/routes/rx.php (used by the patient pages). */
    public ?\Closure $rxModel = null;
    /** Patient overview page, set by core/routes/mycare.php. */
    public ?\Closure $myOverview = null;
    /** Small HTML builders shared by route files (card, btn, stat). @var array<string,\Closure> */
    public array $ui = [];

    public function __construct(string $root)
    {
        self::$i = $this;
        $this->env = new Env();
        $this->env->load($root . '/.env');
        date_default_timezone_set($this->env->get('APP_TIMEZONE', 'Asia/Dhaka'));
        $this->db = new Database($this->env);
        $this->db->run('SET time_zone = ?', [date('P')]);
        $this->auth = new Auth($this->db);
        $this->view = new View($root . '/templates');
        $this->router = new Router();
        $this->tenant = new Tenant($this->env, $this->db);
        $this->site = new Site($this->db);
        $this->plans = new Plans($this->db);
        $this->access = new Access($this->db);
        $this->throttle = new Throttle($this->db);
        $this->mailer = new Mailer($this->env);
        require_once __DIR__ . '/Mail.php';
        $this->mail = new Mail($this);
        $this->domains = new Domains($this->db, $this->env, $this->tenant);
        $this->hostSlug = $this->tenant->slugFromHost();
        if ($this->hostSlug !== null && $this->hostSlug !== '') {
            $this->hostVendor = $this->tenant->bySlug($this->hostSlug);
            if ($this->hostVendor !== null && $this->hostVendor['status'] === 'deleted') {
                $this->hostVendor = null; // soft deleted: the address behaves as if it does not exist
                $this->hostSlug = '';
            }
            if ($this->hostVendor !== null) {
                Currency::$context = Currency::forTenant((string) $this->hostVendor['id']);
                $tz = (string) $this->db->val("SELECT `value` FROM settings WHERE tenant_id = ? AND `key` = 'timezone' LIMIT 1", [(string) $this->hostVendor['id']]);
                if ($tz === '') {
                    $tz = (string) $this->site->get(null, 'default_timezone', '');
                }
                if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true) && $tz !== date_default_timezone_get()) {
                    date_default_timezone_set($tz); // each vendor works in its own time zone
                    $this->db->run('SET time_zone = ?', [date('P')]);
                }
            }
        } else {
            Currency::visitor(); // set the public pricing cookie before any HTML is sent
            $tz = (string) $this->site->get(null, 'default_timezone', '');
            if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true) && $tz !== date_default_timezone_get()) {
                date_default_timezone_set($tz);
                $this->db->run('SET time_zone = ?', [date('P')]);
            }
        }
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
    }

    /** Renders a page and stops. */
    public function error(int $code, string $message, string $title = ''): never
    {
        http_response_code($code);
        $this->view->page('error', ['title' => $title ?: ($code === 404 ? 'Page not found' : 'Something went wrong'), 'code' => $code, 'message' => $message, 'user' => $this->auth->user()]);
        exit;
    }

    /** Vendor type row (database first, then the built-in defaults). @return array<string,mixed> */
    public function typeRow(string $slug): array
    {
        return VendorTypes::find($this->db, $slug) ?? (VendorTypes::defaults()[$slug] ?? VendorTypes::defaults()['hospital']);
    }

    /** Where a signed-in user goes first. */
    public function home(array $user): string
    {
        if (($user['role'] ?? '') === 'Super Admin') {
            return $this->tenant->mainUrl('admin');
        }
        // laboratory roles land on the laboratory home of their branch
        if (in_array($user['role'] ?? '', ['Pathologist', 'Sample Collector', 'Attendant', 'Phlebotomist', 'Branch Admin'], true) && !empty($user['tenant_id'])
            && (string) $this->db->val('SELECT type_slug FROM sc_vendors WHERE tenant_id = ?', [$user['tenant_id']]) === 'clinic'
            && (int) $this->db->val('SELECT COUNT(*) FROM sc_branches WHERE tenant_id = ?', [$user['tenant_id']]) > 0) {
            return url('portal/lab');
        }
        return url('portal');
    }

    /** Address of the sign in page for a vendor (its own host in sub domain mode). */
    public function vendorPortalUrl(array $vendor, string $path = 'portal'): string
    {
        return $this->tenant->suffix() === '' ? url($path) : $this->tenant->siteUrl((string) $vendor['slug'], $path);
    }
}
