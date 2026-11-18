<?php
// Selfcare original code. A small PDF writer in plain PHP. No libraries.
//
// Makes real PDF 1.4 files: A4 pages, the standard Helvetica fonts (WinAnsi text), lines, boxes,
// Code 128 barcodes, JPEG pictures, tables that continue on the next page with the header row repeated,
// and "Page X of Y" in the footer.
//
//   $pdf = new Pdf('Invoice INV-0001');
//   $pdf->header = fn (Pdf $p, int $n) => ...;      // drawn on top of every page, must set $p->y below it
//   $pdf->footer = fn (Pdf $p, int $n, int $total) => ...;
//   $pdf->addPage(); $pdf->text(...); $pdf->table(...);
//   $pdf->output('file.pdf');  // sends the download
declare(strict_types=1);

namespace Selfcare;

final class Pdf
{
    public float $W = 595.28;
    public float $H = 841.89;

    public float $ml = 36.0;
    public float $mr = 36.0;
    public float $mt = 36.0;
    public float $mb = 48.0;
    /** Cursor, measured from the top of the page. */
    public float $y = 36.0;
    /** @var ?callable */
    public $header = null;
    /** @var ?callable */
    public $footer = null;

    /** @var list<string> page content streams */
    private array $pages = [];
    private int $cur = -1;
    /** @var array{regular:list<int>,bold:list<int>} */
    private array $fonts;
    /** @var list<array{data:string,w:int,h:int}> */
    private array $images = [];
    private bool $inHeader = false;

    public function __construct(private string $title = 'Document', private string $author = 'Self Care', bool $landscape = false)
    {
        if ($landscape) {
            [$this->W, $this->H] = [$this->H, $this->W];
        }
        $this->fonts = require __DIR__ . '/config/pdf_fonts.php';
    }

    // ------------------------------------------------------------------ pages

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
        $this->y = $this->mt;
        if ($this->header !== null && !$this->inHeader) {
            $this->inHeader = true;
            ($this->header)($this, $this->cur + 1);
            $this->inHeader = false;
        }
    }

    public function pageNo(): int
    {
        return $this->cur + 1;
    }

    public function contentWidth(): float
    {
        return $this->W - $this->ml - $this->mr;
    }

    public function bottom(): float
    {
        return $this->H - $this->mb;
    }

    /** Starts a new page when less than $need points are left. */
    public function ensure(float $need): void
    {
        if ($this->cur < 0 || $this->y + $need > $this->bottom()) {
            $this->addPage();
        }
    }

    private function put(string $s): void
    {
        $this->pages[$this->cur] .= $s . "\n";
    }

    // ------------------------------------------------------------------ text

    /** UTF-8 to the single byte WinAnsi encoding the PDF fonts use. */
    public static function enc(string $s): string
    {
        $s = strtr($s, ['৳' => 'BDT ', '₹' => 'INR ', '’' => "'", '“' => '"', '”' => '"', '→' => '->', '✓' => 'Yes', '×' => 'x']);
        $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        return $out === false ? preg_replace('/[^\x20-\x7e]/', '?', $s) : $out;
    }

    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $w = $this->fonts[$bold ? 'bold' : 'regular'];
        $t = 0;
        foreach (str_split(self::enc($s)) as $ch) {
            $o = ord($ch);
            $t += $o >= 32 ? ($w[$o - 32] ?? 556) : 0;
        }
        return $t * $size / 1000;
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], self::enc($s));
    }

    /** @param array{0:int,1:int,2:int} $c */
    private static function rg(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0] / 255, $c[1] / 255, $c[2] / 255);
    }

    /**
     * Writes one line of text at x (from left) and y (baseline, from top).
     * $align: L, R or C inside the width $w (when $w > 0).
     * @param array{0:int,1:int,2:int} $color
     */
    public function text(float $x, float $y, string $s, float $size = 9.0, bool $bold = false, array $color = [20, 24, 28], string $align = 'L', float $w = 0.0, bool $italic = false): void
    {
        if ($s === '') {
            return;
        }
        if ($w > 0 && $align !== 'L') {
            $tw = $this->textWidth($s, $size, $bold);
            $x += $align === 'R' ? max(0, $w - $tw) : max(0, ($w - $tw) / 2);
        }
        $font = $bold ? 'F2' : ($italic ? 'F3' : 'F1');
        $this->put(sprintf('BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET', $font, $size, self::rg($color), $x, $this->H - $y, self::esc($s)));
    }

    /** Breaks text into lines that fit $width. @return list<string> */
    public function wrap(string $s, float $width, float $size = 9.0, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $s) ?: [''] as $para) {
            $line = '';
            foreach (preg_split('/\s+/', trim($para)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($try, $size, $bold) <= $width) {
                    $line = $try;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                }
                // very long word: cut it
                while ($this->textWidth($word, $size, $bold) > $width && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $this->textWidth(mb_substr($word, 0, $cut), $size, $bold) > $width) {
                        $cut--;
                    }
                    $lines[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $line = $word;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /** A paragraph at the cursor. Moves the cursor down. Returns the height used. */
    public function paragraph(string $s, float $size = 9.0, bool $bold = false, ?float $width = null, float $x = null, array $color = [20, 24, 28], float $lh = 0.0): float
    {
        $width ??= $this->contentWidth();
        $x ??= $this->ml;
        $lh = $lh ?: $size * 1.35;
        $h = 0.0;
        foreach ($this->wrap($s, $width, $size, $bold) as $l) {
            $this->ensure($lh);
            $this->text($x, $this->y + $size, $l, $size, $bold, $color);
            $this->y += $lh;
            $h += $lh;
        }
        return $h;
    }

    // ------------------------------------------------------------------ shapes

    /** @param ?array{0:int,1:int,2:int} $fill @param ?array{0:int,1:int,2:int} $stroke */
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lw = 0.6, float $radius = 0.0): void
    {
        $py = $this->H - $y - $h;
        $op = $fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S');
        $s = '';
        if ($fill !== null) {
            $s .= self::rg($fill) . ' rg ';
        }
        if ($stroke !== null) {
            $s .= self::rg($stroke) . ' RG ' . sprintf('%.2F w ', $lw);
        }
        if ($radius > 0) {
            $r = min($radius, $w / 2, $h / 2);
            $k = 0.5523 * $r;
            $x2 = $x + $w;
            $y2 = $py + $h;
            $s .= sprintf('%.2F %.2F m %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c h %s',
                $x + $r, $py, $x2 - $r, $py, $x2 - $r + $k, $py, $x2, $py + $r - $k, $x2, $py + $r, $x2, $y2 - $r, $x2, $y2 - $r + $k, $x2 - $r + $k, $y2, $x2 - $r, $y2,
                $x + $r, $y2, $x + $r - $k, $y2, $x, $y2 - $r + $k, $x, $y2 - $r, $x, $py + $r, $x, $py + $r - $k, $x + $r - $k, $py, $x + $r, $py, $op);
        } else {
            $s .= sprintf('%.2F %.2F %.2F %.2F re %s', $x, $py, $w, $h, $op);
        }
        $this->put($s);
    }

    /** @param array{0:int,1:int,2:int} $color */
    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.6, array $color = [200, 205, 210], string $dash = ''): void
    {
        $this->put(sprintf('%s %.2F w %s%.2F %.2F m %.2F %.2F l S%s', self::rg($color) . ' RG', $w, $dash !== '' ? "[$dash] 0 d " : '', $x1, $this->H - $y1, $x2, $this->H - $y2, $dash !== '' ? ' [] 0 d' : ''));
    }

    // ------------------------------------------------------------------ barcode (Code 128 B)

    private const C128 = ['212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412', '211214', '211232', '2331112'];

    /** Module pattern of a Code 128 B barcode: list of bar/space widths starting with a bar. @return list<int> */
    public static function code128(string $text): array
    {
        $codes = [104];
        $sum = 104;
        $i = 1;
        foreach (str_split(preg_replace('/[^\x20-\x7e]/', '', $text) ?? '') as $ch) {
            $v = ord($ch) - 32;
            $codes[] = $v;
            $sum += $v * $i++;
        }
        $codes[] = $sum % 103;
        $codes[] = 106;
        $mods = [];
        foreach ($codes as $c) {
            foreach (str_split(self::C128[$c]) as $d) {
                $mods[] = (int) $d;
            }
        }
        return $mods;
    }

    public function barcode(float $x, float $y, float $w, float $h, string $text): void
    {
        $mods = self::code128($text);
        $total = array_sum($mods);
        $u = $w / $total;
        $cx = $x;
        foreach ($mods as $i => $m) {
            if ($i % 2 === 0) {
                $this->rect($cx, $y, $m * $u, $h, [20, 24, 28]);
            }
            $cx += $m * $u;
        }
    }

    // ------------------------------------------------------------------ images

    /** Draws a JPEG (path or raw bytes). Returns false when the file is not a usable JPEG. */
    public function jpeg(string $bytesOrPath, float $x, float $y, float $w, float $h): bool
    {
        $data = is_file($bytesOrPath) ? (string) file_get_contents($bytesOrPath) : $bytesOrPath;
        $info = @getimagesizefromstring($data);
        if (!$info || $info[2] !== IMAGETYPE_JPEG || ($info['channels'] ?? 3) === 4) {
            return false;
        }
        $this->images[] = ['data' => $data, 'w' => (int) $info[0], 'h' => (int) $info[1]];
        $n = count($this->images);
        $this->put(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $w, $h, $x, $this->H - $y - $h, $n));
        return true;
    }

    /** Any image the PHP GD extension can read, converted to JPEG (white background). */
    public function image(string $path, float $x, float $y, float $w, float $h): bool
    {
        if (!is_file($path)) {
            return false;
        }
        if ($this->jpeg($path, $x, $y, $w, $h)) {
            return true;
        }
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $im = @imagecreatefromstring((string) file_get_contents($path));
        if ($im === false) {
            return false;
        }
        $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($bg, 0, 0, (int) imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        ob_start();
        imagejpeg($bg, null, 90);
        return $this->jpeg((string) ob_get_clean(), $x, $y, $w, $h);
    }

    // ------------------------------------------------------------------ tables

    /**
     * Table with a repeated header row and zebra stripes.
     * @param list<array{label:string,w:float,align?:string}> $cols widths are relative numbers
     * @param list<array<int,string>|array{cells:array<int,string>,bold?:bool,fill?:array}> $rows
     * @param array<string,mixed> $o size, head_fill, head_color, zebra, pad
     */
    public function table(array $cols, array $rows, array $o = []): void
    {
        $size = (float) ($o['size'] ?? 8.5);
        $pad = (float) ($o['pad'] ?? 4.0);
        $total = array_sum(array_column($cols, 'w')) ?: 1;
        $cw = $this->contentWidth();
        $widths = array_map(static fn ($c) => $c['w'] / $total * $cw, $cols);
        $headFill = $o['head_fill'] ?? [16, 24, 32];
        $headColor = $o['head_color'] ?? [255, 255, 255];
        $lh = $size * 1.3;
        $drawHead = function () use ($cols, $widths, $size, $pad, $headFill, $headColor, $lh): void {
            $h = $lh + $pad * 2;
            $this->ensure($h + $lh * 2);
            $x = $this->ml;
            $this->rect($x, $this->y, array_sum($widths), $h, $headFill);
            foreach ($cols as $i => $c) {
                $this->text($x + $pad, $this->y + $pad + $size, $c['label'], $size, true, $headColor, $c['align'] ?? 'L', $widths[$i] - $pad * 2);
                $x += $widths[$i];
            }
            $this->y += $h;
        };
        $drawHead();
        $n = 0;
        foreach ($rows as $row) {
            $cells = $row['cells'] ?? $row;
            $bold = !empty($row['bold']);
            $lines = [];
            $max = 1;
            foreach ($cols as $i => $c) {
                $lines[$i] = $this->wrap((string) ($cells[$i] ?? ''), $widths[$i] - $pad * 2, $size, $bold);
                $max = max($max, count($lines[$i]));
            }
            $h = $max * $lh + $pad * 2;
            if ($this->y + $h > $this->bottom()) {
                $this->addPage();
                $drawHead();
            }
            $fill = $row['fill'] ?? (($o['zebra'] ?? true) && $n % 2 === 1 ? [246, 248, 249] : null);
            $x = $this->ml;
            if ($fill !== null) {
                $this->rect($x, $this->y, array_sum($widths), $h, $fill);
            }
            foreach ($cols as $i => $c) {
                foreach ($lines[$i] as $k => $l) {
                    $this->text($x + $pad, $this->y + $pad + $size + $k * $lh, $l, $size, $bold, [20, 24, 28], $c['align'] ?? 'L', $widths[$i] - $pad * 2);
                }
                $x += $widths[$i];
            }
            $this->line($this->ml, $this->y + $h, $this->ml + array_sum($widths), $this->y + $h, 0.4, [226, 230, 233]);
            $this->y += $h;
            $n++;
        }
        $this->y += 4;
    }

    // ------------------------------------------------------------------ output

    /** Complete PDF file as a string. */
    public function render(): string
    {
        if ($this->cur < 0) {
            $this->addPage();
        }
        $total = count($this->pages);
        if ($this->footer !== null) {
            $keep = $this->cur;
            for ($i = 0; $i < $total; $i++) {
                $this->cur = $i;
                ($this->footer)($this, $i + 1, $total);
            }
            $this->cur = $keep;
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $nImg = count($this->images);
        // object numbers: 1 catalog, 2 pages, 3-5 fonts, 6 info, 7.. images, then per page (page, content)
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
        $objs[6] = '<< /Title (' . self::esc($this->title) . ') /Author (' . self::esc($this->author) . ') /Creator (Self Care) /Producer (Self Care PDF) /CreationDate (D:' . date('YmdHis') . ") >>";
        $xobj = '';
        foreach ($this->images as $i => $im) {
            $no = 7 + $i;
            $objs[$no] = ['dict' => sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode', $im['w'], $im['h']), 'stream' => $im['data'], 'raw' => true];
            $xobj .= sprintf('/Im%d %d 0 R ', $i + 1, $no);
        }
        // gray JPEG needs DeviceGray
        foreach ($this->images as $i => $im) {
            $info = @getimagesizefromstring($im['data']);
            if (($info['channels'] ?? 3) === 1) {
                $objs[7 + $i]['dict'] = str_replace('DeviceRGB', 'DeviceGray', $objs[7 + $i]['dict']);
            }
        }
        $next = 7 + $nImg;
        foreach ($this->pages as $content) {
            $pg = $next++;
            $ct = $next++;
            $kids[] = $pg . ' 0 R';
            $objs[$pg] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /XObject << %s>> >> /Contents %d 0 R >>', $this->W, $this->H, $xobj, $ct);
            $objs[$ct] = ['dict' => '<< /Filter /FlateDecode', 'stream' => (string) gzcompress($content, 6), 'raw' => false];
        }
        $objs[2] = '<< /Type /Pages /Count ' . count($kids) . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $no => $o) {
            $offsets[$no] = strlen($out);
            if (is_array($o)) {
                $out .= $no . " 0 obj\n" . $o['dict'] . ' /Length ' . strlen($o['stream']) . " >>\nstream\n" . $o['stream'] . "\nendstream\nendobj\n";
            } else {
                $out .= $no . " 0 obj\n" . $o . "\nendobj\n";
            }
        }
        $xref = strlen($out);
        $count = max(array_keys($objs)) + 1;
        $out .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= "trailer\n<< /Size $count /Root 1 0 R /Info 6 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }

    /** Sends the file to the browser as a download (or inline), or saves it when $save is a path. */
    public function output(string $filename, bool $inline = false, ?string $save = null): void
    {
        $bytes = $this->render();
        if ($save !== null) {
            file_put_contents($save, $bytes);
            return;
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bytes;
    }
}
