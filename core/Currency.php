<?php
// Selfcare original code. Currencies: how an amount is written (symbol, decimals, separators) and how it is spelt in words.
// The table sc_currencies is the single source. money(), Billing::fmt() and Documents::amountWords() all use it.
declare(strict_types=1);

namespace Selfcare;

final class Currency
{
    /** Code used by money() when no code is passed: the vendor's currency inside a portal or vendor website, else the platform default. */
    public static ?string $context = null;
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $rows = null;
    private static ?string $visitor = null;
    /** Tables that store a currency code next to an amount. */
    public const MONEY_TABLES = ['sc_ledger', 'sc_records', 'sc_stock_items', 'sc_invoices', 'sc_lab_orders', 'sc_lab_order_items', 'sc_commissions', 'sc_lab_home', 'sc_staff_profiles', 'sc_salary', 'sc_salary_adj'];
    /** Rates a vendor set for its own consolidated reports: currency code => units of the vendor currency for 1 unit of that code. */
    public static array $vendorFx = [];
    /** Vendor currency those rates are written in. */
    public static string $vendorFxBase = '';

    /**
     * Built in rows. Used to seed the table and as a fallback when the table is not there yet.
     * rate = units of the currency for 1 US dollar. The rates are indicative starting values: Self Care edits them under Currencies.
     * @return list<array<string,mixed>>
     */
    public static function defaults(): array
    {
        // code, name, symbol, major, minor, grouping, symbol position, decimals, rate per 1 USD
        $t = [
            ['PKR', 'Pakistani Rupee', 'Rs', 'Rupees', 'Paisa', 'lakh', 'before', 2, 280], ['BDT', 'Bangladeshi Taka', '৳', 'Taka', 'Paisa', 'lakh', 'before', 2, 122], ['USD', 'US Dollar', '$', 'Dollars', 'Cents', 'standard', 'before', 2, 1],
            ['INR', 'Indian Rupee', '₹', 'Rupees', 'Paise', 'lakh', 'before', 2, 85], ['CAD', 'Canadian Dollar', 'CA$', 'Dollars', 'Cents', 'standard', 'before', 2, 1.38], ['EUR', 'Euro', '€', 'Euro', 'Cents', 'standard', 'before', 2, 0.92],
            ['GBP', 'British Pound', '£', 'Pounds', 'Pence', 'standard', 'before', 2, 0.78], ['AED', 'UAE Dirham', 'AED', 'Dirhams', 'Fils', 'standard', 'before', 2, 3.6725], ['SAR', 'Saudi Riyal', 'SAR', 'Riyals', 'Halalas', 'standard', 'before', 2, 3.75],
            ['AUD', 'Australian Dollar', 'A$', 'Dollars', 'Cents', 'standard', 'before', 2, 1.52], ['MYR', 'Malaysian Ringgit', 'RM', 'Ringgit', 'Sen', 'standard', 'before', 2, 4.4], ['SGD', 'Singapore Dollar', 'S$', 'Dollars', 'Cents', 'standard', 'before', 2, 1.34],
            ['NPR', 'Nepalese Rupee', 'NPR', 'Rupees', 'Paisa', 'lakh', 'before', 2, 135], ['LKR', 'Sri Lankan Rupee', 'LKR', 'Rupees', 'Cents', 'standard', 'before', 2, 300], ['QAR', 'Qatari Riyal', 'QAR', 'Riyals', 'Dirhams', 'standard', 'before', 2, 3.64],
            ['KWD', 'Kuwaiti Dinar', 'KWD', 'Dinars', 'Fils', 'standard', 'before', 3, 0.307], ['BHD', 'Bahraini Dinar', 'BHD', 'Dinars', 'Fils', 'standard', 'before', 3, 0.376], ['OMR', 'Omani Rial', 'OMR', 'Rials', 'Baisa', 'standard', 'before', 3, 0.385],
            ['JOD', 'Jordanian Dinar', 'JOD', 'Dinars', 'Fils', 'standard', 'before', 3, 0.709], ['AFN', 'Afghan Afghani', '؋', 'Afghanis', 'Pul', 'standard', 'before', 2, 70], ['IQD', 'Iraqi Dinar', 'IQD', 'Dinars', '', 'standard', 'before', 0, 1310],
            ['EGP', 'Egyptian Pound', 'E£', 'Pounds', 'Piastres', 'standard', 'before', 2, 49], ['TRY', 'Turkish Lira', '₺', 'Lira', 'Kurus', 'standard', 'before', 2, 34], ['CNY', 'Chinese Yuan', 'CN¥', 'Yuan', 'Fen', 'standard', 'before', 2, 7.2],
            ['JPY', 'Japanese Yen', '¥', 'Yen', '', 'standard', 'before', 0, 150], ['KRW', 'South Korean Won', '₩', 'Won', '', 'standard', 'before', 0, 1400], ['HKD', 'Hong Kong Dollar', 'HK$', 'Dollars', 'Cents', 'standard', 'before', 2, 7.8],
            ['THB', 'Thai Baht', '฿', 'Baht', 'Satang', 'standard', 'before', 2, 35], ['IDR', 'Indonesian Rupiah', 'Rp', 'Rupiah', '', 'standard', 'before', 0, 15800], ['PHP', 'Philippine Peso', '₱', 'Pesos', 'Centavos', 'standard', 'before', 2, 57],
            ['VND', 'Vietnamese Dong', '₫', 'Dong', '', 'standard', 'after', 0, 25000], ['MMK', 'Myanmar Kyat', 'K', 'Kyats', 'Pya', 'standard', 'before', 0, 2100], ['NZD', 'New Zealand Dollar', 'NZ$', 'Dollars', 'Cents', 'standard', 'before', 2, 1.65],
            ['CHF', 'Swiss Franc', 'CHF', 'Francs', 'Rappen', 'standard', 'before', 2, 0.88], ['SEK', 'Swedish Krona', 'kr', 'Kronor', 'Ore', 'standard', 'after', 2, 10.5], ['NOK', 'Norwegian Krone', 'kr', 'Kroner', 'Ore', 'standard', 'after', 2, 10.8],
            ['DKK', 'Danish Krone', 'kr', 'Kroner', 'Ore', 'standard', 'after', 2, 6.9], ['RUB', 'Russian Ruble', '₽', 'Rubles', 'Kopecks', 'standard', 'after', 2, 95], ['BRL', 'Brazilian Real', 'R$', 'Reais', 'Centavos', 'standard', 'before', 2, 5.5],
            ['MXN', 'Mexican Peso', 'MX$', 'Pesos', 'Centavos', 'standard', 'before', 2, 18], ['ZAR', 'South African Rand', 'R', 'Rand', 'Cents', 'standard', 'before', 2, 18], ['NGN', 'Nigerian Naira', '₦', 'Naira', 'Kobo', 'standard', 'before', 2, 1500],
            ['KES', 'Kenyan Shilling', 'KSh', 'Shillings', 'Cents', 'standard', 'before', 2, 129], ['GHS', 'Ghanaian Cedi', 'GH₵', 'Cedis', 'Pesewas', 'standard', 'before', 2, 15], ['TZS', 'Tanzanian Shilling', 'TSh', 'Shillings', 'Cents', 'standard', 'before', 0, 2600],
            ['UGX', 'Ugandan Shilling', 'USh', 'Shillings', '', 'standard', 'before', 0, 3700], ['ETB', 'Ethiopian Birr', 'Br', 'Birr', 'Santim', 'standard', 'before', 2, 125], ['MAD', 'Moroccan Dirham', 'MAD', 'Dirhams', 'Centimes', 'standard', 'before', 2, 10],
        ];
        $out = [];
        foreach ($t as $i => [$c, $n, $s, $maj, $min, $grp, $pos, $dec, $rate]) {
            $out[] = ['code' => $c, 'name' => $n, 'symbol' => $s, 'decimals' => $dec, 'symbol_pos' => $pos, 'thousand_sep' => ',', 'decimal_sep' => '.', 'grouping' => $grp, 'major_name' => $maj, 'minor_name' => $min, 'is_enabled' => 1, 'sort_order' => $i + 1, 'rate' => $rate];
        }
        return $out;
    }

    /** Adds the built in currencies that are missing, and gives the rate to currencies that never had one. Returns how many were added. */
    public static function seed(Database $db): int
    {
        $n = 0;
        $hasRate = (bool) $db->all("SHOW COLUMNS FROM sc_currencies LIKE 'rate'");
        foreach (self::defaults() as $r) {
            if ((int) $db->val('SELECT COUNT(*) FROM sc_currencies WHERE code = ?', [$r['code']]) === 0) {
                if (!$hasRate) {
                    unset($r['rate']);
                }
                $db->insert('sc_currencies', $r);
                $n++;
            } elseif ($hasRate && $r['code'] !== 'USD') {
                $db->run('UPDATE sc_currencies SET rate = ? WHERE code = ? AND rate_updated_at IS NULL AND rate = 1', [$r['rate'], $r['code']]);
            }
        }
        return $n;
    }

    /** Units of the currency for 1 US dollar (never 0). */
    public static function rate(string $code): float
    {
        $r = (float) (self::all()[$code]['rate'] ?? 1);
        return $r > 0 ? $r : 1.0;
    }

    /** Value of $amount given in $from, written in $to. Same currency = same number. */
    public static function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to || $from === '') {
            return $amount;
        }
        return round($amount / self::effRate($from) * self::effRate($to), max(0, min(4, (int) (self::all()[$to]['decimals'] ?? 2))));
    }

    /**
     * SQL that gives an amount column in the currency $target, whatever currency the row was written in (NULL = already $target).
     * Use inside SUM(): SUM(Currency::sql('i.total', 'i.currency', 'PKR')).
     */
    public static function sql(string $amountCol, string $currencyCol, string $target): string
    {
        $t = str_replace("'", '', $target);
        $rt = number_format(self::effRate($t), 8, '.', '');
        $own = '(SELECT c.rate FROM sc_currencies c WHERE c.code = ' . $currencyCol . ' COLLATE utf8mb4_unicode_ci)';
        if (self::$vendorFx && self::$vendorFxBase !== '') {
            $case = '';
            foreach (self::$vendorFx as $code => $k) {
                $case .= " WHEN '" . str_replace("'", '', $code) . "' THEN " . number_format(self::rate(self::$vendorFxBase) / $k, 8, '.', '');
            }
            $own = "(CASE $currencyCol COLLATE utf8mb4_unicode_ci" . $case . " ELSE $own END)";
        }
        return "IF($currencyCol IS NULL OR $currencyCol = '$t', $amountCol, $amountCol / COALESCE(NULLIF($own, 0), 1) * $rt)";
    }

    /** Writes the amount columns of rows (which carry their own currency code, or none) in the currency $target. @param list<array<string,mixed>> $rows @param list<string> $keys @return list<array<string,mixed>> */
    public static function normalize(array $rows, array $keys, string $target, string $curKey = 'currency'): array
    {
        foreach ($rows as &$r) {
            $from = (string) ($r[$curKey] ?? '');
            if ($from !== '' && $from !== $target) {
                foreach ($keys as $k) {
                    if (isset($r[$k])) {
                        $r[$k] = self::convert((float) $r[$k], $from, $target);
                    }
                }
                $r[$curKey] = $target;
            }
        }
        return $rows;
    }

    /**
     * Run once on upgrade, before the defaults change: every vendor that never chose a currency keeps the one it was showing,
     * and money rows without a code get their vendor's code. Nothing a vendor sees changes.
     */
    public static function pinExisting(Database $db): int
    {
        $legacy = (string) $db->val("SELECT `value` FROM front_settings WHERE tenant_id IS NULL AND `key` = 'platform_currency' LIMIT 1");
        $legacy = $legacy !== '' && isset(self::all()[$legacy]) ? $legacy : 'USD';
        $n = 0;
        foreach ($db->all("SELECT t.id FROM tenants t WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.tenant_id = t.id AND s.`key` = 'currency')") as $t) {
            $db->run("INSERT INTO settings (tenant_id, `key`, `value`, created_at, updated_at) VALUES (?, 'currency', ?, NOW(), NOW())", [$t['id'], $legacy]);
            $n++;
        }
        foreach (['sc_ledger', 'sc_records', 'sc_stock_items'] as $tbl) {
            $db->run("UPDATE $tbl x SET x.currency = COALESCE((SELECT s.`value` FROM settings s WHERE s.tenant_id = x.tenant_id AND s.`key` = 'currency' LIMIT 1), ?) WHERE x.currency IS NULL", [$legacy]);
        }
        return $n;
    }

    /** @return array<string,array<string,mixed>> keyed by code */
    public static function all(bool $enabledOnly = false): array
    {
        if (self::$rows === null) {
            self::$rows = [];
            try {
                foreach (app()->db->all('SELECT * FROM sc_currencies ORDER BY sort_order, code') as $r) {
                    self::$rows[(string) $r['code']] = $r;
                }
            } catch (\Throwable) {
            }
            if (!self::$rows) {
                foreach (self::defaults() as $r) {
                    self::$rows[$r['code']] = $r;
                }
            }
        }
        return $enabledOnly ? array_filter(self::$rows, static fn ($r) => (int) $r['is_enabled'] === 1) : self::$rows;
    }

    public static function reset(): void
    {
        self::$rows = null;
        self::$visitor = null;
    }

    /** Adds the vendor's currency when a new money row does not name one. @param array<string,mixed> $data @return array<string,mixed> */
    public static function stamp(string $table, array $data): array
    {
        if (!in_array($table, self::MONEY_TABLES, true) || (isset($data['currency']) && (string) $data['currency'] !== '')) {
            return $data;
        }
        $tid = (string) ($data['tenant_id'] ?? '');
        if ($tid !== '') {
            try {
                if ($table === 'sc_salary_adj' && !empty($data['salary_id'])) {
                    $c = (string) app()->db->val('SELECT currency FROM sc_salary WHERE id = ?', [(int) $data['salary_id']]);
                    $data['currency'] = $c !== '' ? $c : self::forTenant($tid);
                } else {
                    $data['currency'] = self::forBranch($tid, isset($data['branch_id']) ? (int) $data['branch_id'] : 0);
                }
            } catch (\Throwable) {
            }
        }
        return $data;
    }

    /** Platform default (plans, subscription invoices). */
    public static function platform(): string
    {
        $c = '';
        try {
            $c = (string) app()->site->get(null, 'platform_currency', '');
        } catch (\Throwable) {
        }
        return isset(self::all()[$c]) ? $c : (isset(self::all()['PKR']) ? 'PKR' : 'USD');
    }

    /** Currency a visitor of the public site looks at: ?cur=XXX (remembered in a cookie), else the platform default. Call once before any output so the cookie can be set. */
    public static function visitor(): string
    {
        if (self::$visitor !== null) {
            return self::$visitor;
        }
        $c = strtoupper((string) ($_GET['cur'] ?? ''));
        if ($c !== '' && isset(self::all(true)[$c])) {
            if (!headers_sent()) {
                setcookie('sc_cur', $c, ['expires' => time() + 86400 * 90, 'path' => '/', 'samesite' => 'Lax']);
            }
            return self::$visitor = $c;
        }
        $c = strtoupper((string) ($_COOKIE['sc_cur'] ?? ''));
        return self::$visitor = ($c !== '' && isset(self::all(true)[$c]) ? $c : self::platform());
    }

    /** Currency a vendor chose in its settings, else the platform default. */
    public static function forTenant(?string $tenantId): string
    {
        if ($tenantId === null || $tenantId === '') {
            return self::platform();
        }
        $c = (string) app()->db->val("SELECT `value` FROM settings WHERE tenant_id = ? AND `key` = 'currency' LIMIT 1", [$tenantId]);
        return isset(self::all()[$c]) ? $c : self::platform();
    }

    /** Currency of one clinic branch: its own when set, else the vendor's. */
    public static function forBranch(?string $tenantId, int $branchId): string
    {
        if ($tenantId === null || $tenantId === '') {
            return self::platform();
        }
        if ($branchId > 0) {
            try {
                $c = (string) app()->db->val('SELECT currency FROM sc_branches WHERE id = ? AND tenant_id = ?', [$branchId, $tenantId]);
                if ($c !== '' && isset(self::all()[$c])) {
                    return $c;
                }
            } catch (\Throwable) {
            }
        }
        return self::forTenant($tenantId);
    }

    /** Loads the vendor's own exchange rates (settings key fx_rates, a JSON map) used when branches in different currencies are reported together. */
    public static function loadVendorFx(string $tenantId): void
    {
        self::$vendorFx = [];
        self::$vendorFxBase = self::forTenant($tenantId);
        $j = (string) app()->db->val("SELECT `value` FROM settings WHERE tenant_id = ? AND `key` = 'fx_rates' LIMIT 1", [$tenantId]);
        $m = $j !== '' ? json_decode($j, true) : null;
        if (is_array($m)) {
            foreach ($m as $code => $k) {
                if (isset(self::all()[(string) $code]) && (float) $k > 0) {
                    self::$vendorFx[(string) $code] = (float) $k;
                }
            }
        }
    }

    /** Units of the currency for 1 US dollar, honouring the vendor's own rates when they were loaded. */
    private static function effRate(string $code): float
    {
        if (self::$vendorFx && self::$vendorFxBase !== '') {
            if ($code === self::$vendorFxBase) {
                return self::rate($code);
            }
            if (isset(self::$vendorFx[$code])) {
                return self::rate(self::$vendorFxBase) / self::$vendorFx[$code];
            }
        }
        return self::rate($code);
    }

    public static function find(string $code): array
    {
        return self::all()[$code] ?? ['code' => $code, 'name' => $code, 'symbol' => $code . ' ', 'decimals' => 2, 'symbol_pos' => 'before', 'thousand_sep' => ',', 'decimal_sep' => '.', 'grouping' => 'standard', 'major_name' => $code, 'minor_name' => '', 'is_enabled' => 1];
    }

    /** Select options of enabled currencies (plus $keep so an old choice never disappears). @return array<string,string> */
    public static function options(?string $keep = null): array
    {
        $o = [];
        foreach (self::all(true) as $c => $r) {
            $o[$c] = $r['name'] . ' (' . trim((string) $r['symbol']) . ')';
        }
        if ($keep !== null && $keep !== '' && !isset($o[$keep])) {
            $o[$keep] = self::find($keep)['name'] . ' (' . $keep . ')';
        }
        return $o;
    }

    private static function group(string $int, string $sep, string $grouping): string
    {
        if ($grouping === 'lakh' && strlen($int) > 3) {
            $last = substr($int, -3);
            $rest = substr($int, 0, -3);
            return preg_replace('/\B(?=(\d{2})+(?!\d))/', $sep, $rest) . $sep . $last;
        }
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', $sep, $int) ?? $int;
    }

    /** "Rs 12,50,000.00". $trim drops ".00" for whole amounts (short labels). */
    public static function fmt(float $amount, ?string $code = null, bool $trim = false): string
    {
        $c = self::find($code ?? self::$context ?? self::platform());
        $dec = max(0, min(4, (int) $c['decimals']));
        $neg = $amount < 0;
        $abs = round(abs($amount), $dec);
        if ($trim && $abs == floor($abs)) {
            $dec = 0;
        }
        $parts = explode('.', number_format($abs, $dec, '.', ''));
        $num = self::group($parts[0], (string) $c['thousand_sep'], (string) $c['grouping']) . ($dec > 0 ? (string) $c['decimal_sep'] . $parts[1] : '');
        $sym = trim((string) $c['symbol']);
        $s = $c['symbol_pos'] === 'after' ? $num . ' ' . $sym : $sym . (preg_match('/[A-Za-z]$/', $sym) ? ' ' : '') . $num;
        return ($neg ? '-' : '') . $s;
    }

    /** "Five Thousand Rupees and Fifty Paisa Only". */
    public static function words(float $amount, ?string $code = null): string
    {
        $c = self::find($code ?? self::platform());
        $dec = max(0, min(4, (int) $c['decimals']));
        $amount = round(abs($amount), $dec);
        $whole = (int) floor($amount);
        $minor = (int) round(($amount - $whole) * (10 ** $dec));
        $s = Documents::words($whole) . ' ' . $c['major_name'];
        if ($minor > 0 && (string) $c['minor_name'] !== '') {
            $s .= ' and ' . Documents::words($minor) . ' ' . $c['minor_name'];
        }
        return $s . ' Only';
    }
}
