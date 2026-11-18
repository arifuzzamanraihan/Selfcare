<?php
// Selfcare original code. Structured prescriptions: the data rules, safety checks, history and the print model.
// Tables: sc_prescriptions, sc_prescription_items, sc_rx_templates. Pages are in core/routes/rx.php.
// Rules: a prescription belongs to one vendor; its patient and doctor must belong to the same vendor; a draft can be edited or deleted;
// an issued prescription is locked (copy it to change it) and can only be cancelled with a reason.
declare(strict_types=1);

namespace Selfcare;

final class Rx
{
    /** @var array<string,mixed> */
    private static array $cfg = [];
    /** @var list<string> */
    private static array $drugs = [];

    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> */
    public static function cfg(): array
    {
        return self::$cfg ?: (self::$cfg = require __DIR__ . '/config/rx.php');
    }

    /** @return list<string> */
    public static function drugs(): array
    {
        return self::$drugs ?: (self::$drugs = require __DIR__ . '/config/rx_drugs.php');
    }

    /** Settings for this kind of vendor, or null when it does not write prescriptions. @return array<string,mixed>|null */
    public static function forType(string $slug): ?array
    {
        return self::cfg()['types'][$slug] ?? null;
    }

    public function code(string $tenant): string
    {
        $n = (int) $this->db->val("SELECT COALESCE(MAX(CAST(SUBSTRING(rx_no, 4) AS UNSIGNED)), 0) FROM sc_prescriptions WHERE tenant_id = ? AND rx_no LIKE 'RX-%'", [$tenant]);
        return 'RX-' . str_pad((string) ($n + 1), 5, '0', STR_PAD_LEFT);
    }

    /** @return array<string,mixed>|null */
    public function find(string $tenant, int $id): ?array
    {
        return $this->db->one('SELECT * FROM sc_prescriptions WHERE id = ? AND tenant_id = ?', [$id, $tenant]);
    }

    /** @return list<array<string,mixed>> */
    public function items(string $tenant, int $rxId): array
    {
        return $this->db->all('SELECT * FROM sc_prescription_items WHERE tenant_id = ? AND prescription_id = ? ORDER BY line_no, id', [$tenant, $rxId]);
    }

    /** The doctors row of a signed-in user, if they are a doctor of this vendor. @return array<string,mixed>|null */
    public function doctorOf(string $tenant, int $userId): ?array
    {
        return $this->db->one('SELECT d.id, d.specialist, d.reg_no, u.first_name, u.last_name, u.qualification FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? AND d.user_id = ? AND u.deleted_at IS NULL', [$tenant, $userId]);
    }

    /** @return list<array<string,mixed>> */
    public function doctors(string $tenant): array
    {
        return $this->db->all('SELECT d.id, d.specialist, d.reg_no, u.first_name, u.last_name, u.qualification FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.tenant_id = ? AND u.deleted_at IS NULL AND u.status = 1 ORDER BY u.first_name, u.last_name', [$tenant]);
    }

    /** @return list<array<string,mixed>> */
    public function patients(string $tenant, int $limit = 600): array
    {
        return $this->db->all('SELECT p.id, p.patient_unique_id, p.allergies, p.conditions, u.first_name, u.last_name, u.phone, u.gender, u.dob FROM patients p JOIN users u ON u.id = p.user_id WHERE p.tenant_id = ? AND u.deleted_at IS NULL ORDER BY u.first_name, u.last_name LIMIT ' . $limit, [$tenant]);
    }

    public static function doctorLabel(array $d): string
    {
        return 'Dr. ' . trim($d['first_name'] . ' ' . $d['last_name']);
    }

    public static function doctorInfo(array $d): string
    {
        return implode(' · ', array_filter([trim((string) ($d['specialist'] ?? '')), trim((string) ($d['qualification'] ?? '')), ($d['reg_no'] ?? '') !== '' ? 'Reg. no ' . $d['reg_no'] : '']));
    }

    public static function age(?string $dob): string
    {
        if (!$dob || strtotime($dob) === false) {
            return '';
        }
        $d = (new \DateTimeImmutable($dob))->diff(new \DateTimeImmutable('today'));
        return $d->y > 0 ? (string) $d->y . ' y' : ($d->m > 0 ? $d->m . ' m' : $d->d . ' d');
    }

    // ------------------------------------------------------------------ safety checks

    /** Words in the allergy text that match a prescribed medicine (directly or by drug class). @param list<string> $drugs @return list<string> messages */
    public static function allergyHits(string $allergies, array $drugs): array
    {
        $allergies = mb_strtolower(trim($allergies));
        if ($allergies === '' || in_array($allergies, ['none', 'no', 'nil', 'nkda', 'no known allergies', 'no known drug allergies'], true)) {
            return [];
        }
        $words = array_values(array_filter(array_map('trim', preg_split('/[,;\n\/]+| and /u', $allergies) ?: []), static fn ($w) => mb_strlen($w) >= 3));
        $classes = self::cfg()['classes'];
        $out = [];
        foreach ($drugs as $drug) {
            $dl = mb_strtolower($drug);
            foreach ($words as $w) {
                $hit = str_contains($dl, $w) || str_contains($w, preg_replace('/\s+\d.*$/', '', $dl) ?: $dl);
                if (!$hit) {
                    foreach ($classes as $cls => $members) {
                        if (str_contains($w, $cls) && array_filter($members, static fn ($m) => str_contains($dl, $m))) {
                            $hit = true;
                            break;
                        }
                    }
                }
                if ($hit) {
                    $out[] = $drug . ' may cause a reaction (allergy: ' . $w . ')';
                    break;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /** @param list<string> $drugs @return list<string> */
    public static function duplicates(array $drugs): array
    {
        $seen = [];
        $out = [];
        foreach ($drugs as $d) {
            $k = mb_strtolower(trim(preg_replace('/\s+/', ' ', $d) ?: $d));
            if (isset($seen[$k])) {
                $out[] = $d . ' is on the prescription twice';
            }
            $seen[$k] = true;
        }
        return array_values(array_unique($out));
    }

    // ------------------------------------------------------------------ input

    /** Cleans the posted medicine lines. @param array<string,mixed> $post @return list<array<string,mixed>> */
    public static function postedItems(array $post): array
    {
        $out = [];
        $cols = ['drug' => 190, 'strength' => 60, 'form' => 40, 'route' => 30, 'dose' => 60, 'freq' => 60, 'timing' => 40, 'dur_unit' => 10, 'qty' => 30, 'indication' => 120, 'instruction' => 255];
        $n = is_array($post['drug'] ?? null) ? count($post['drug']) : 0;
        for ($i = 0; $i < $n && count($out) < 30; $i++) {
            $row = [];
            foreach ($cols as $c => $max) {
                $row[$c] = mb_substr(trim((string) (($post[$c] ?? [])[$i] ?? '')), 0, $max);
            }
            if ($row['drug'] === '') {
                continue;
            }
            $dn = (int) (($post['dur_n'] ?? [])[$i] ?? 0);
            $row['dur_n'] = $dn > 0 ? min($dn, 999) : null;
            if ($row['dur_n'] === null) {
                $row['dur_unit'] = $row['dur_unit'] === 'ongoing' ? 'ongoing' : '';
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Validates a prescription before it is issued. @return list<string> problems */
    public static function issueProblems(array $rx, array $items): array
    {
        $p = [];
        if (!$items) {
            $p[] = 'Add at least one medicine before you issue it.';
        }
        if (trim((string) $rx['diagnosis']) === '' && trim((string) $rx['complaints']) === '') {
            $p[] = 'Write the complaints or the diagnosis.';
        }
        if (empty($rx['doctor_id'])) {
            $p[] = 'Choose the doctor.';
        }
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if (trim((string) $it['dose']) === '' || trim((string) $it['freq']) === '') {
                $p[] = 'Medicine ' . $n . ' (' . $it['drug'] . '): write the dose and how often.';
            }
            if (empty($it['dur_n']) && ($it['dur_unit'] ?? '') !== 'ongoing' && trim((string) $it['qty']) === '' && !str_contains(mb_strtolower((string) $it['freq']), 'single') && !str_contains(mb_strtolower((string) $it['freq']), 'prn')) {
                $p[] = 'Medicine ' . $n . ' (' . $it['drug'] . '): write how long, or the quantity.';
            }
        }
        return $p;
    }

    // ------------------------------------------------------------------ history

    /** All prescriptions of one patient, newest first. @return list<array<string,mixed>> */
    public function forPatient(string $tenant, int $patientId): array
    {
        return $this->db->all("SELECT r.*, (SELECT GROUP_CONCAT(i.drug ORDER BY i.line_no SEPARATOR ', ') FROM sc_prescription_items i WHERE i.prescription_id = r.id AND i.tenant_id = r.tenant_id) AS drugs FROM sc_prescriptions r WHERE r.tenant_id = ? AND r.patient_id = ? AND r.deleted_at IS NULL ORDER BY r.rx_date DESC, r.id DESC", [$tenant, $patientId]);
    }

    /** Medicines the patient is on now: from issued prescriptions whose course has not ended. @return list<array<string,mixed>> */
    public function currentMedicines(string $tenant, int $patientId): array
    {
        $rows = $this->db->all("SELECT i.*, r.rx_date, r.rx_no, r.id AS rx_id FROM sc_prescription_items i JOIN sc_prescriptions r ON r.id = i.prescription_id AND r.tenant_id = i.tenant_id WHERE r.tenant_id = ? AND r.patient_id = ? AND r.status = 'issued' AND r.deleted_at IS NULL ORDER BY r.rx_date DESC, i.line_no", [$tenant, $patientId]);
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $k = mb_strtolower((string) $r['drug']);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $days = (int) ($r['dur_n'] ?? 0) * match ((string) $r['dur_unit']) { 'weeks' => 7, 'months' => 30, default => 1 };
            $ends = $days > 0 ? date('Y-m-d', strtotime((string) $r['rx_date'] . ' +' . $days . ' days')) : null;
            if ($r['dur_unit'] === 'ongoing' || ($ends !== null && $ends >= date('Y-m-d'))) {
                $r['ends'] = $ends;
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Medicines this vendor used before, for the suggestion list. @return list<string> */
    public function usedDrugs(string $tenant): array
    {
        return array_column($this->db->all('SELECT drug, COUNT(*) n FROM sc_prescription_items WHERE tenant_id = ? GROUP BY drug ORDER BY n DESC, drug LIMIT 200', [$tenant]), 'drug');
    }

    // ------------------------------------------------------------------ medicine master (sc_medicines)

    /** Copies the starter list into a vendor's own list the first time it is needed. Returns how many were added. */
    public function seedMeds(string $tenant): int
    {
        if ((int) $this->db->val('SELECT COUNT(*) FROM sc_medicines WHERE tenant_id = ?', [$tenant]) > 0) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach (require __DIR__ . '/config/medicines.php' as $m) {
            $this->db->insert('sc_medicines', ['tenant_id' => $tenant, 'generic' => $m['generic'], 'brand' => $m['brand'] ?: null, 'strength' => $m['strength'] ?: null, 'form' => $m['form'] ?: null,
                'drug_class' => $m['class'] ?: null, 'route' => $m['route'] ?: null, 'dose' => $m['dose'] ?: null, 'freq' => $m['freq'] ?: null, 'timing' => $m['timing'] ?: null,
                'dur_n' => $m['dur_n'], 'dur_unit' => $m['dur_unit'] ?: null, 'active' => 1, 'source' => 'seed', 'created_at' => $now, 'updated_at' => $now]);
            $n++;
        }
        return $n;
    }

    /** The name printed on a prescription for a master entry: first brand (generic), or just the generic. */
    public static function medLabel(array $m): string
    {
        $b = trim((string) explode('/', (string) ($m['brand'] ?? ''))[0]);
        return $b !== '' ? $b . ' (' . $m['generic'] . ')' : (string) $m['generic'];
    }

    /** Active entries for the search box in the prescription editor. @return list<array<string,mixed>> */
    public function medsForPicker(string $tenant): array
    {
        $this->seedMeds($tenant);
        return $this->db->all('SELECT * FROM sc_medicines WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY generic, brand, strength', [$tenant]);
    }

    /** Adds a medicine typed in a prescription to the master when no entry matches its name, so the next search finds it. */
    public function rememberMeds(string $tenant, array $items, int $userId): int
    {
        $n = 0;
        foreach ($items as $it) {
            $name = trim((string) ($it['drug'] ?? ''));
            if ($name === '') {
                continue;
            }
            $bare = trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $name));
            $hit = $this->db->val("SELECT id FROM sc_medicines WHERE tenant_id = ? AND deleted_at IS NULL AND (generic = ? OR generic = ? OR brand LIKE ? OR brand LIKE ?) LIMIT 1", [$tenant, $name, $bare, $bare . '%', '%/ ' . $bare . '%']);
            if ($hit) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $this->db->insert('sc_medicines', ['tenant_id' => $tenant, 'generic' => mb_substr($name, 0, 190), 'strength' => ($it['strength'] ?? '') ?: null, 'form' => ($it['form'] ?? '') ?: null, 'route' => ($it['route'] ?? '') ?: null,
                'dose' => ($it['dose'] ?? '') ?: null, 'freq' => ($it['freq'] ?? '') ?: null, 'timing' => ($it['timing'] ?? '') ?: null, 'dur_n' => ($it['dur_n'] ?? null) ?: null, 'dur_unit' => ($it['dur_unit'] ?? '') ?: null,
                'active' => 1, 'source' => 'auto', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------------ print model

    /** @return array<string,mixed> */
    public function model(App $app, string $tenant, ?array $rx, array $items, ?array $user, array $typeCfg, ?array $patient = null): array
    {
        $blank = $rx === null;
        $v = $rx['vitals'] ?? null;
        $vit = $v ? (array) json_decode((string) $v, true) : [];
        $extra = !empty($rx['extra']) ? (array) json_decode((string) $rx['extra'], true) : [];
        $blocks = [];
        $blocks[] = ['kv', 'title' => 'Patient', 'items' => [
            ['Name', (string) ($rx['patient_name'] ?? '')],
            ['Patient ID', (string) ($patient['patient_unique_id'] ?? '')],
            ['Age / sex', trim((string) ($rx['age'] ?? '') . ((($rx['age'] ?? '') !== '' && ($rx['sex'] ?? '') !== '') ? ' / ' : '') . (string) ($rx['sex'] ?? ''))],
            ['Weight', ($rx['weight'] ?? '') !== '' && $rx['weight'] !== null ? rtrim(rtrim((string) $rx['weight'], '0'), '.') . ' kg' : ''],
            ['Phone', (string) ($rx['patient_phone'] ?? '')],
            ['Date', $rx ? dt((string) $rx['rx_date']) : ''],
        ]];
        $info = (string) ($rx['doctor_info'] ?? '');
        $blocks[] = ['kv', 'title' => 'Doctor', 'items' => [['Doctor', (string) ($rx['doctor_name'] ?? '')], ['Details', $info]]];
        $dv = [];
        foreach (['bp' => 'BP', 'pulse' => 'Pulse', 'temp' => 'Temp', 'spo2' => 'SpO2', 'height' => 'Height'] as $k => $l) {
            if (($vit[$k] ?? '') !== '') {
                $dv[] = [$l, (string) $vit[$k]];
            }
        }
        foreach ($typeCfg['extra'] ?? [] as $x) {
            if (($extra[$x[0]] ?? '') !== '') {
                $val = (string) $extra[$x[0]];
                $dv[] = [(string) $x[1], ($x[2] ?? '') === 'date' ? dt($val) : $val];
            }
        }
        if ($dv || $blank) {
            $blocks[] = ['kv', 'title' => 'Examination', 'items' => $dv ?: [['BP', ''], ['Pulse', ''], ['Temp', ''], ['SpO2', '']]];
        }
        if (!$blank && trim((string) $rx['allergies']) !== '') {
            $blocks[] = ['text', 'title' => 'Allergies', 'body' => (string) $rx['allergies']];
        }
        foreach (['complaints' => 'Complaints', 'diagnosis' => 'Diagnosis'] as $k => $l) {
            if ($blank) {
                $blocks[] = ['lines', 'title' => $l, 'n' => 2];
            } elseif (trim((string) $rx[$k]) !== '') {
                $blocks[] = ['text', 'title' => $l, 'body' => (string) $rx[$k]];
            }
        }
        $rows = [];
        foreach ($items as $i => $it) {
            $name = trim((string) $it['drug'] . ((string) $it['strength'] !== '' && !str_contains(mb_strtolower((string) $it['drug']), mb_strtolower((string) $it['strength'])) ? ' ' . $it['strength'] : ''));
            $name .= (string) $it['form'] !== '' ? ' (' . $it['form'] . ')' : '';
            $dur = !empty($it['dur_n']) ? $it['dur_n'] . ' ' . ($it['dur_unit'] ?: 'days') : (($it['dur_unit'] ?? '') === 'ongoing' ? 'Ongoing' : '');
            $how = trim((string) $it['dose'] . ((string) $it['route'] !== '' && !str_starts_with((string) $it['route'], 'PO') ? ' · ' . $it['route'] : ''));
            $ins = implode('. ', array_filter([(string) $it['timing'], (string) $it['instruction'], (string) $it['indication'] !== '' ? 'For ' . $it['indication'] : '', (string) $it['qty'] !== '' ? 'Qty ' . $it['qty'] : '']));
            $rows[] = [(string) ($i + 1), $name, $how, (string) $it['freq'], $dur, $ins];
        }
        $blocks[] = ['table', 'title' => 'Rx  Medicines', 'cols' => [['#', 3], ['Medicine', 30], ['Dose', 14], ['How often', 16], ['For', 9], ['How to take', 28]], 'rows' => $rows, 'grid' => $blank, 'min' => 6];
        foreach (['investigations' => 'Tests advised', 'advice' => 'Advice'] as $k => $l) {
            if ($blank) {
                $blocks[] = ['lines', 'title' => $l, 'n' => 2];
            } elseif (trim((string) $rx[$k]) !== '') {
                $blocks[] = ['text', 'title' => $l, 'body' => (string) $rx[$k]];
            }
        }
        if (!$blank && !empty($rx['followup_date'])) {
            $blocks[] = ['kv', 'title' => 'Follow-up', 'items' => [['Come back on', dt((string) $rx['followup_date'])]]];
        }
        if (!empty($typeCfg['note'])) {
            $blocks[] = ['note', 'body' => (string) $typeCfg['note']];
        }
        $blocks[] = ['note', 'body' => 'Take medicines exactly as written. Do not share them. Keep out of reach of children. Bring this prescription to your next visit.'];
        $docLine = $rx && trim((string) $rx['doctor_name']) !== '' ? trim((string) $rx['doctor_name']) : 'Doctor';
        $status = $rx ? ($rx['status'] === 'issued' ? '' : strtoupper((string) $rx['status'])) : '';
        return [
            'org' => Documents::org($app, $tenant), 'title' => (string) ($typeCfg['title'] ?? 'PRESCRIPTION'), 'no' => (string) ($rx['rx_no'] ?? ''), 'date' => $rx ? dt((string) $rx['rx_date']) : '',
            'status' => $status, 'blocks' => $blocks, 'sign' => [$docLine], 'printed_by' => $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '', 'printed_at' => date('d M Y, H:i'),
        ];
    }
}
