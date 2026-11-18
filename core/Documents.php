<?php
// Selfcare original code. Turns a "document model" into a PDF (and the same model is shown as HTML for print).
//
// Model (all keys optional unless noted):
//   org      name, address, phone, email, reg_no, logo (path under public/), tagline
//   title    printed heading, no (document number), date, status (small stamp text)
//   landscape bool
//   blocks   list of blocks:
//     ['kv', title?, items => [[label, value], ...]]            two column details box; empty value = blank line
//     ['table', title?, cols => [[label, width, align?]], rows => [cells | ['cells'=>..., 'bold'=>true]], grid? => true (blank form rows)]
//     ['totals', rows => [[label, value, bold?]]]
//     ['words', text]
//     ['text', title?, body]
//     ['note', body]                                             small grey paragraph
//   terms    text printed under the blocks
//   sign     list of signature box names (a stamp box is added)
//   printed_by, printed_at, generator
declare(strict_types=1);

namespace Selfcare;

final class Documents
{
    public const ACCENT = [14, 124, 111];

    /** Whole number to English words. */
    public static function words(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $chunk = static function (int $x) use ($ones, $tens): string {
            $s = '';
            if ($x >= 100) {
                $s .= $ones[intdiv($x, 100)] . ' Hundred';
                $x %= 100;
                $s .= $x ? ' ' : '';
            }
            if ($x >= 20) {
                $s .= $tens[intdiv($x, 10)] . ($x % 10 ? '-' . $ones[$x % 10] : '');
            } elseif ($x > 0) {
                $s .= $ones[$x];
            }
            return $s;
        };
        $out = [];
        foreach ([1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand', 1 => ''] as $v => $name) {
            if ($n >= $v) {
                $q = intdiv($n, $v);
                $n %= $v;
                $out[] = trim($chunk($q) . ' ' . $name);
            }
        }
        return implode(' ', $out);
    }

    /** "Five Hundred Dollars and Fifty Cents Only". */
    public static function amountWords(float $amount, string $currency = 'USD'): string
    {
        return Currency::words($amount, $currency);
    }

    /** Organisation block for the header. @return array<string,string> */
    public static function org(App $app, ?string $tenantId): array
    {
        if ($tenantId === null) {
            $s = static fn (string $k, string $d = '') => $app->site->get(null, $k, $d);
            return ['name' => $s('site_name', 'Self Care'), 'address' => $s('contact_address'), 'phone' => $s('contact_phone'), 'email' => $s('contact_email'), 'reg_no' => $s('registration_no'), 'logo' => $s('logo'), 'tagline' => $s('tagline')];
        }
        $row = $app->db->one('SELECT t.hospital_name, v.city, v.phone FROM tenants t LEFT JOIN sc_vendors v ON v.tenant_id = t.id WHERE t.id = ?', [$tenantId]);
        $s = static fn (string $k, string $d = '') => $app->site->get($tenantId, $k, $d);
        $addr = trim($s('address') . ($row['city'] ?? '' ? ', ' . $row['city'] : ''), ', ');
        return ['name' => (string) ($row['hospital_name'] ?? ''), 'address' => $addr, 'phone' => $s('phone', (string) ($row['phone'] ?? '')), 'email' => $s('email'), 'reg_no' => $s('license_no'), 'logo' => $s('logo'), 'tagline' => $s('tagline')];
    }

    /** Builds the PDF object for a model. @param array<string,mixed> $m */
    public static function pdf(array $m): Pdf
    {
        $org = $m['org'] ?? [];
        $pdf = new Pdf((string) ($m['title'] ?? 'Document') . ' ' . ($m['no'] ?? ''), (string) ($org['name'] ?? 'Self Care'), !empty($m['landscape']));
        $A = self::ACCENT;
        $W = $pdf->W;
        $pdf->header = static function (Pdf $p, int $n) use ($m, $org, $A): void {
            $x = $p->ml;
            $cw = $p->contentWidth();
            if ($n === 1) {
                // logo
                $logo = !empty($org['logo']) ? dirname(__DIR__) . '/public/' . ltrim((string) $org['logo'], '/') : '';
                if (!($logo !== '' && $p->image($logo, $x, $p->mt, 44, 44))) {
                    $p->rect($x, $p->mt, 44, 44, $A, null, 0.6, 9);
                    $p->text($x, $p->mt + 29, initials((string) ($org['name'] ?? 'SC')), 17, true, [255, 255, 255], 'C', 44);
                }
                $tx = $x + 56;
                $p->text($tx, $p->mt + 13, (string) ($org['name'] ?? ''), 15, true, [16, 24, 32]);
                $y = $p->mt + 26;
                foreach (array_filter([(string) ($org['address'] ?? ''), trim(((string) ($org['phone'] ?? '')) . (($org['email'] ?? '') !== '' ? '   ' . $org['email'] : '')), ($org['reg_no'] ?? '') !== '' ? 'Registration / licence no: ' . $org['reg_no'] : '']) as $line) {
                    $p->text($tx, $y, $line, 8, false, [90, 100, 110]);
                    $y += 10.5;
                }
                // title block on the right
                $rw = 200;
                $rx = $x + $cw - $rw;
                $p->text($rx, $p->mt + 13, (string) ($m['title'] ?? ''), 12.5, true, $A, 'R', $rw);
                if (($m['no'] ?? '') !== '') {
                    $p->text($rx, $p->mt + 27, 'No: ' . $m['no'], 9, true, [20, 24, 28], 'R', $rw);
                }
                $p->text($rx, $p->mt + 39, 'Date: ' . ($m['date'] ?? ''), 8.5, false, [60, 70, 80], 'R', $rw);
                if (!empty($m['no'])) {
                    $p->barcode($rx + $rw - 104, $p->mt + 46, 104, 15, (string) $m['no']);
                }
                if (!empty($m['status'])) {
                    $p->text($rx - 70, $p->mt + 13, strtoupper((string) $m['status']), 8, true, [150, 160, 170], 'R', 60);
                }
                $p->line($x, $p->mt + 70, $x + $cw, $p->mt + 70, 1.2, $A);
                $p->y = $p->mt + 80;
            } else {
                $p->text($x, $p->mt + 9, (string) ($org['name'] ?? ''), 10, true, [16, 24, 32]);
                $p->text($x, $p->mt + 9, ($m['title'] ?? '') . (($m['no'] ?? '') !== '' ? '   No: ' . $m['no'] : ''), 9, false, [90, 100, 110], 'R', $cw);
                $p->line($x, $p->mt + 17, $x + $cw, $p->mt + 17, 0.8, $A);
                $p->y = $p->mt + 26;
            }
        };
        $by = (string) ($m['printed_by'] ?? '');
        $at = (string) ($m['printed_at'] ?? date('d M Y, H:i'));
        $pdf->footer = static function (Pdf $p, int $n, int $t) use ($by, $at, $m): void {
            $y = $p->H - $p->mb + 14;
            $p->line($p->ml, $y - 8, $p->W - $p->mr, $y - 8, 0.5, [200, 205, 210]);
            $left = 'Printed' . ($by !== '' ? ' by ' . $by : '') . ' on ' . $at;
            $p->text($p->ml, $y + 2, $left, 7, false, [110, 120, 130]);
            $p->text($p->ml, $y + 2, (string) ($m['generator'] ?? 'Generated by Self Care'), 7, false, [110, 120, 130], 'C', $p->contentWidth());
            $p->text($p->ml, $y + 2, 'Page ' . $n . ' of ' . $t, 7, true, [60, 70, 80], 'R', $p->contentWidth());
        };
        $pdf->addPage();
        $cw = $pdf->contentWidth();
        foreach ($m['blocks'] ?? [] as $b) {
            self::block($pdf, $b, $cw, $A);
        }
        if (!empty($m['terms'])) {
            $pdf->y += 4;
            $pdf->ensure(40);
            $pdf->text($pdf->ml, $pdf->y + 8, 'Terms and notes', 8, true, [60, 70, 80]);
            $pdf->y += 12;
            $pdf->paragraph((string) $m['terms'], 7.5, false, null, null, [90, 100, 110]);
        }
        if (!empty($m['sign'])) {
            $pdf->y += 10;
            $pdf->ensure(80);
            $y = $pdf->y + 46;
            $names = array_values((array) $m['sign']);
            $n = count($names) + 1;
            $gap = 16;
            $bw = ($cw - $gap * ($n - 1)) / $n;
            foreach ($names as $i => $name) {
                $x = $pdf->ml + $i * ($bw + $gap);
                $pdf->line($x, $y, $x + $bw, $y, 0.7, [60, 70, 80]);
                $pdf->text($x, $y + 10, (string) $name, 8, false, [60, 70, 80], 'C', $bw);
            }
            $x = $pdf->ml + count($names) * ($bw + $gap);
            $pdf->rect($x + $bw / 2 - 28, $y - 40, 56, 44, null, [190, 196, 202], 0.6, 6);
            $pdf->text($x, $y + 10, 'Stamp', 8, false, [140, 150, 160], 'C', $bw);
            $pdf->y = $y + 18;
        }
        return $pdf;
    }

    /** @param array<int|string,mixed> $b */
    private static function block(Pdf $p, array $b, float $cw, array $A): void
    {
        $type = $b[0];
        $title = $b['title'] ?? null;
        if ($title !== null && !in_array($type, ['text', 'lines'], true)) {
            $p->ensure(34);
            $p->text($p->ml, $p->y + 9, strtoupper((string) $title), 8, true, $A);
            $p->y += 14;
        }
        switch ($type) {
            case 'kv':
                $items = $b['items'];
                $half = (int) ceil(count($items) / 2);
                $colW = ($cw - 14) / 2;
                $cols = [array_slice($items, 0, $half), array_slice($items, $half)];
                $size = 8.5;
                $heights = [];
                foreach ($cols as $ci => $list) {
                    $h = 0.0;
                    foreach ($list as $it) {
                        $lines = max(1, count($p->wrap((string) ($it[1] ?? ''), $colW - 96, $size)));
                        $h += max(14, $lines * 11.5 + 3);
                    }
                    $heights[$ci] = $h;
                }
                $H = max($heights ?: [0]) + 10;
                $p->ensure($H + 4);
                $p->rect($p->ml, $p->y, $cw, $H, [247, 249, 250], [226, 230, 233], 0.5, 4);
                foreach ($cols as $ci => $list) {
                    $x = $p->ml + 8 + $ci * ($colW + 14);
                    $y = $p->y + 6;
                    foreach ($list as $it) {
                        $val = (string) ($it[1] ?? '');
                        $lines = $p->wrap($val, $colW - 96, $size);
                        $p->text($x, $y + $size + 1, (string) $it[0], 7.5, false, [100, 110, 120]);
                        if ($val === '') {
                            $p->line($x + 90, $y + $size + 2, $x + $colW - 6, $y + $size + 2, 0.5, [150, 158, 166]);
                            $y += 16;
                            continue;
                        }
                        foreach ($lines as $k => $l) {
                            $p->text($x + 90, $y + $size + 1 + $k * 11.5, $l, $size, true, [20, 24, 28]);
                        }
                        $y += max(14, count($lines) * 11.5 + 3);
                    }
                }
                $p->y += $H + 8;
                break;
            case 'table':
                $cols = [];
                foreach ($b['cols'] as $c) {
                    $cols[] = ['label' => (string) $c[0], 'w' => (float) $c[1], 'align' => $c[2] ?? 'L'];
                }
                $rows = $b['rows'];
                if (!empty($b['grid'])) {
                    $min = (int) ($b['min'] ?? 6);
                    while (count($rows) < $min) {
                        $rows[] = array_fill(0, count($cols), ' ');
                    }
                    foreach ($rows as &$r) {
                        if (isset($r['cells'])) {
                            continue;
                        }
                        $r = ['cells' => array_map(static fn ($c) => ($c === '' ? ' ' : $c), $r), 'blank' => true];
                    }
                    unset($r);
                }
                $p->table($cols, $rows, ['size' => $b['size'] ?? 8.3, 'pad' => !empty($b['grid']) ? 7.5 : 3.8, 'head_fill' => $A, 'zebra' => empty($b['grid'])]);
                if (!$rows) {
                    $p->text($p->ml, $p->y + 8, 'No records for this selection.', 8.5, false, [110, 120, 130], 'L', 0, true);
                    $p->y += 18;
                }
                break;
            case 'totals':
                $w = 230;
                $x = $p->ml + $cw - $w;
                $h = count($b['rows']) * 15 + 6;
                $p->ensure($h + 6);
                foreach ($b['rows'] as $i => $r) {
                    $bold = !empty($r[2]);
                    $y = $p->y + 3 + $i * 15;
                    if ($bold) {
                        $p->rect($x, $y - 2, $w, 16, $A);
                    }
                    $col = $bold ? [255, 255, 255] : [20, 24, 28];
                    $p->text($x + 6, $y + 9, (string) $r[0], 8.5, $bold, $bold ? $col : [90, 100, 110]);
                    $p->text($x, $y + 9, (string) $r[1], 8.8, true, $col, 'R', $w - 6);
                }
                $p->y += $h + 4;
                break;
            case 'words':
                $p->ensure(24);
                $p->text($p->ml, $p->y + 9, 'Amount in words:', 8, true, [90, 100, 110]);
                $p->y += 11;
                $p->paragraph((string) $b['text'], 9, true, null, null, [20, 24, 28]);
                $p->y += 4;
                break;
            case 'text':
                if ($title !== null) {
                    $p->ensure(30);
                    $p->text($p->ml, $p->y + 9, strtoupper((string) $title), 8, true, $A);
                    $p->y += 13;
                }
                $p->paragraph((string) $b['body'], 9, false, null, null, [20, 24, 28], 12.5);
                $p->y += 6;
                break;
            case 'lines':
                $n = (int) ($b['n'] ?? 3);
                if ($title !== null) {
                    $p->ensure(30 + $n * 16);
                    $p->text($p->ml, $p->y + 9, (string) $title, 8, true, [90, 100, 110]);
                    $p->y += 10;
                }
                for ($i = 0; $i < $n; $i++) {
                    $p->y += 16;
                    $p->line($p->ml, $p->y, $p->ml + $cw, $p->y, 0.4, [170, 176, 182], '1 2');
                }
                $p->y += 8;
                break;
            case 'note':
                $p->paragraph((string) $b['body'], 7.8, false, null, null, [100, 110, 120]);
                $p->y += 4;
                break;
        }
    }

    /** HTML sheet of the same model (screen view and browser print). @param array<string,mixed> $m */
    public static function html(array $m): string
    {
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $org = $m['org'] ?? [];
        $logo = !empty($org['logo']) ? base_path() . '/' . ltrim((string) $org['logo'], '/') : base_path() . '/img/logo-mark.svg';
        $bc = '';
        if (!empty($m['no'])) {
            $mods = Pdf::code128((string) $m['no']);
            $tot = array_sum($mods);
            $x = 0;
            $bars = '';
            foreach ($mods as $i => $w) {
                if ($i % 2 === 0) {
                    $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="26" fill="#111"/>';
                }
                $x += $w;
            }
            $bc = '<svg viewBox="0 0 ' . $tot . ' 26" width="150" height="26" preserveAspectRatio="none" role="img" aria-label="Barcode ' . $e($m['no']) . '">' . $bars . '</svg>';
        }
        $h = '<article class="dx-sheet">';
        if (($m['status'] ?? '') === 'VOID') {
            $h .= '<div class="dx-void">VOID</div>';
        }
        $h .= '<div class="dx-head"><img src="' . $e($logo) . '" alt="">' . '<div class="t"><b>' . $e($org['name'] ?? '') . '</b>';
        foreach (array_filter([$org['address'] ?? '', trim(($org['phone'] ?? '') . ' ' . ($org['email'] ?? '')), !empty($org['reg_no']) ? 'Registration / licence no: ' . $org['reg_no'] : '']) as $l) {
            $h .= '<span>' . $e($l) . '</span>';
        }
        $h .= '</div></div><h1>' . $e($m['title'] ?? '') . '</h1><div class="dx-meta"><div>' . (!empty($m['no']) ? '<b>No: ' . $e($m['no']) . '</b><br>' : '') . 'Date: ' . $e($m['date'] ?? '') . (!empty($m['status']) ? ' &nbsp; [' . $e($m['status']) . ']' : '') . '</div><div>' . $bc . '</div></div>';
        foreach ($m['blocks'] ?? [] as $b) {
            $type = $b[0];
            if (!empty($b['title']) && !in_array($type, ['text', 'lines'], true)) {
                $h .= '<h4>' . $e($b['title']) . '</h4>';
            }
            switch ($type) {
                case 'kv':
                    $h .= '<div class="dx-kv">';
                    foreach ($b['items'] as $it) {
                        $h .= '<div><b>' . $e($it[0]) . '</b><span>' . $e($it[1] ?? '') . '</span></div>';
                    }
                    $h .= '</div>';
                    break;
                case 'table':
                    $h .= '<table class="dx-t"><thead><tr>';
                    foreach ($b['cols'] as $c) {
                        $h .= '<th' . (($c[2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . $e($c[0]) . '</th>';
                    }
                    $h .= '</tr></thead><tbody>';
                    $rows = $b['rows'];
                    $pad = !empty($b['grid']) ? max(0, (int) ($b['min'] ?? 0) - count($rows)) : 0;
                    foreach ($rows as $r) {
                        $h .= '<tr>';
                        foreach ($r as $i => $cell) {
                            $h .= '<td' . (($b['cols'][$i][2] ?? 'L') === 'R' ? ' class="r"' : '') . '>' . $e($cell) . '</td>';
                        }
                        $h .= '</tr>';
                    }
                    for ($i = 0; $i < $pad; $i++) {
                        $h .= '<tr>' . str_repeat('<td>&nbsp;</td>', count($b['cols'])) . '</tr>';
                    }
                    if (!$rows && !$pad) {
                        $h .= '<tr><td colspan="' . count($b['cols']) . '" style="color:#777">Nothing recorded.</td></tr>';
                    }
                    $h .= '</tbody></table>';
                    break;
                case 'totals':
                    $h .= '<div class="dx-tot">';
                    foreach ($b['rows'] as $r) {
                        $h .= '<div' . (!empty($r[2]) ? ' class="g"' : '') . '><span>' . $e($r[0]) . '</span><span>' . $e($r[1]) . '</span></div>';
                    }
                    $h .= '</div>';
                    break;
                case 'words':
                    $h .= '<p class="dx-words">In words: ' . $e($b['text']) . '</p>';
                    break;
                case 'text':
                    $h .= (!empty($b['title']) ? '<h4>' . $e($b['title']) . '</h4>' : '') . '<p>' . nl2br($e($b['body'])) . '</p>';
                    break;
                case 'lines':
                    $h .= '<h4>' . $e($b['title']) . '</h4>' . str_repeat('<div style="border-bottom:1px solid #aaa;height:22px"></div>', (int) ($b['n'] ?? 3));
                    break;
                case 'note':
                    $h .= '<p class="dx-note">' . $e($b['body']) . '</p>';
                    break;
            }
        }
        if (!empty($m['terms'])) {
            $h .= '<h4>Terms and notes</h4><p class="dx-note">' . $e($m['terms']) . '</p>';
        }
        if (!empty($m['sign'])) {
            $h .= '<div class="dx-sign">';
            foreach ((array) $m['sign'] as $n) {
                $h .= '<div>' . $e($n) . '</div>';
            }
            $h .= '<div class="dx-stamp">Stamp</div></div>';
        }
        $h .= '<div class="dx-foot"><span>Printed' . (!empty($m['printed_by']) ? ' by ' . $e($m['printed_by']) : '') . ' on ' . $e($m['printed_at'] ?? date('d M Y, H:i')) . '</span><span>Generated by Self Care</span></div></article>';
        return $h;
    }

    /** Sends the PDF of a model. */
    public static function send(array $m, string $filename, bool $inline = false): never
    {
        self::pdf($m)->output($filename, $inline);
        exit;
    }
}
