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

    /** Built in rows. Used to seed the table and as a fallback when the table is not there yet. @return list<array<string,mixed>> */
    public static function defaults(): array
    {
        $r = static fn (string $c, string $n, string $s, string $maj, string $min, string $grp = 'standard', string $pos = 'before', int $dec = 2, int $o = 0): array => ['code' => $c, 'name' => $n, 'symbol' => $s, 'decimals' => $dec, 'symbol_pos' => $pos, 'thousand_sep' => ',', 'decimal_sep' => '.', 'grouping' => $grp, 'major_name' => $maj, 'minor_name' => $min, 'is_enabled' => 1, 'sort_order' => $o];
        return [
            $r('PKR', 'Pakistani Rupee', 'Rs', 'Rupees', 'Paisa', 'lakh', 'before', 2, 1), $r('BDT', 'Bangladeshi Taka', '৳', 'Taka', 'Paisa', 'lakh', 'before', 2, 2), $r('USD', 'US Dollar', '$', 'Dollars', 'Cents', 'standard', 'before', 2, 3),
            $r('INR', 'Indian Rupee', '₹', 'Rupees', 'Paise', 'lakh', 'before', 2, 4), $r('CAD', 'Canadian Dollar', 'CA$', 'Dollars', 'Cents', 'standard', 'before', 2, 5), $r('EUR', 'Euro', '€', 'Euro', 'Cents', 'standard', 'before', 2, 6),
            $r('GBP', 'British Pound', '£', 'Pounds', 'Pence', 'standard', 'before', 2, 7), $r('AED', 'UAE Dirham', 'AED', 'Dirhams', 'Fils', 'standard', 'before', 2, 8), $r('SAR', 'Saudi Riyal', 'SAR', 'Riyals', 'Halalas', 'standard', 'before', 2, 9),
        ];
    }

    public static function seed(Database $db): int
    {
        $n = 0;
        foreach (self::defaults() as $r) {
            if ((int) $db->val('SELECT COUNT(*) FROM sc_currencies WHERE code = ?', [$r['code']]) === 0) {
                $db->insert('sc_currencies', $r);
                $n++;
            }
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
    }

    /** Platform default (plans, subscription invoices). */
    public static function platform(): string
    {
        $c = '';
        try {
            $c = (string) app()->site->get(null, 'platform_currency', '');
        } catch (\Throwable) {
        }
        return isset(self::all()[$c]) ? $c : 'USD';
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
