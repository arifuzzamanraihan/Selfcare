<?php
// Selfcare original code. Generic list / add / edit / delete for one table.
// A module is just a config array (see core/modules.php and docs/ui-system.md).
declare(strict_types=1);

namespace Selfcare;

final class Crud
{
    /** @param array<string,mixed> $m module config */
    public function __construct(private Database $db, private array $m)
    {
    }

    public function url(string $suffix = ''): string
    {
        return url($this->m['url'] . ($suffix !== '' ? '/' . ltrim($suffix, '/') : ''));
    }

    private function table(): string
    {
        return '`' . $this->m['table'] . '`';
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $where = ['id = ?'];
        $p = [$id];
        foreach ($this->m['scope'] ?? [] as $c => $v) {
            $where[] = $v === null ? "`$c` IS NULL" : "`$c` = ?";
            if ($v !== null) {
                $p[] = $v;
            }
        }
        return $this->db->one('SELECT * FROM ' . $this->table() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1', $p);
    }

    /** Listing HTML. */
    public function list(): string
    {
        $m = $this->m;
        $where = [];
        $params = [];
        foreach ($m['scope'] ?? [] as $c => $v) {
            $where[] = $v === null ? "t.`$c` IS NULL" : "t.`$c` = ?";
            if ($v !== null) {
                $params[] = $v;
            }
        }
        $cols = [];
        foreach ($m['columns'] as $key => $col) {
            $cols[$key] = $col + ['sort' => 't.`' . $key . '`'];
        }
        $actions = [];
        if (empty($m['readonly'])) {
            $actions[] = ['label' => 'Edit', 'icon' => 'edit', 'url' => fn ($r) => $this->url((string) $r['id']), 'method' => 'get'];
            $actions[] = ['label' => 'Delete', 'icon' => 'trash', 'danger' => true, 'method' => 'post', 'confirm' => 'Delete this ' . strtolower($m['singular']) . '? This cannot be undone.', 'url' => fn ($r) => $this->url($r['id'] . '/delete')];
        }
        foreach ($m['extra_actions'] ?? [] as $a) {
            $actions[] = $a;
        }
        return Listing::render($this->db, [
            'id' => $m['key'],
            'select' => 't.*' . ($m['select_extra'] ?? ''),
            'from' => $this->table() . ' t' . ($m['join'] ?? ''),
            'where' => $where,
            'params' => $params,
            'columns' => $cols,
            'search' => $m['search'] ?? [],
            'search_hint' => 'Search ' . strtolower($m['title']),
            'filters' => $m['filters'] ?? [],
            'sort' => $m['sort'] ?? array_key_first($cols),
            'dir' => $m['dir'] ?? 'asc',
            'actions' => $actions,
            'badges' => $m['badges'] ?? [],
            'empty' => ['title' => 'No ' . strtolower($m['title']) . ' yet', 'text' => $m['empty'] ?? 'Add the first one to get started.', 'cta' => empty($m['readonly']) ? ['label' => 'Add ' . strtolower($m['singular']), 'url' => $this->url('new')] : null],
        ]);
    }

    /** Form HTML for a new or existing row. */
    public function form(?int $id): string
    {
        $row = $id ? $this->find($id) : null;
        $h = '<form class="d-form" method="post" action="' . e($this->url('save')) . '" enctype="multipart/form-data">' . csrf_field();
        if ($id) {
            $h .= '<input type="hidden" name="id" value="' . $id . '">';
        }
        $h .= '<div class="d-form-grid">';
        foreach ($this->m['fields'] as $name => $f) {
            $val = $row[$name] ?? ($f['default'] ?? '');
            $val = old($name, (string) $val);
            $h .= self::field($name, $f, $val);
        }
        $h .= '</div><div class="d-form-actions d-form-actions-sticky"><a class="d-btn d-btn-soft" href="' . e($this->url()) . '">Cancel</a><button class="d-btn d-btn-primary" type="submit">' . ($id ? 'Save changes' : 'Add ' . strtolower($this->m['singular'])) . '</button></div></form>';
        clear_old();
        return $h;
    }

    /** One form field. @param array<string,mixed> $f */
    public static function field(string $name, array $f, string $val): string
    {
        $type = $f['type'] ?? 'text';
        $label = e($f['label'] ?? ucfirst($name));
        $req = !empty($f['required']) ? ' required' : '';
        $help = !empty($f['help']) ? '<small class="d-help">' . e($f['help']) . '</small>' : '';
        $wide = in_array($type, ['textarea', 'checks', 'image', 'teeth'], true) || !empty($f['wide']) ? ' is-wide' : '';
        $star = !empty($f['required']) ? ' <span class="d-req" aria-hidden="true">*</span>' : '';
        $id = 'f_' . $name;
        $in = '';
        switch ($type) {
            case 'textarea':
                $in = '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($f['rows'] ?? 4) . '"' . $req . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '') . '>' . e($val) . '</textarea>';
                break;
            case 'select':
                $extra = !empty($f['fill']) ? ' data-fill-' . e((string) $f['fill']) : '';
                $search = count($f['options'] ?? []) > 12 ? ' data-searchable' : '';
                $in = '<select id="' . $id . '" name="' . e($name) . '"' . $req . $extra . $search . '>';
                if (empty($f['required']) || ($f['blank'] ?? false)) {
                    $in .= '<option value="">' . e((string) ($f['blank_label'] ?? 'Choose…')) . '</option>';
                }
                foreach ($f['options'] as $k => $lab) {
                    $dm = '';
                    foreach (($f['option_meta'][(string) $k] ?? []) as $ak => $av) {
                        $dm .= ' data-' . e((string) $ak) . '="' . e((string) $av) . '"';
                    }
                    $in .= '<option value="' . e((string) $k) . '"' . ((string) $k === $val ? ' selected' : '') . $dm . '>' . e((string) $lab) . '</option>';
                }
                $in .= '</select>';
                break;
            case 'checks':
                $set = array_filter(array_map('trim', explode(',', $val)));
                $in = '<div class="d-checks">';
                foreach ($f['options'] as $k => $lab) {
                    $in .= '<label class="d-check"><input type="checkbox" name="' . e($name) . '[]" value="' . e((string) $k) . '"' . (in_array((string) $k, $set, true) ? ' checked' : '') . '><span>' . e((string) $lab) . '</span></label>';
                }
                $in .= '</div>';
                $label = '<span>' . $label . '</span>';
                return '<div class="d-field' . $wide . '"><div class="d-label">' . $label . '</div>' . $in . $help . '</div>';
            case 'toggle':
                return '<div class="d-field' . $wide . '"><label class="d-switch"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($val === '1' ? ' checked' : '') . '><span class="d-switch-ui"></span><span>' . $label . '</span></label>' . $help . '</div>';
            case 'image':
                $cur = $val !== '' ? '<img class="d-thumb" src="' . e(base_path() . '/' . ltrim($val, '/')) . '" alt="">' : '';
                $in = $cur . '<input id="' . $id . '" type="file" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="' . e($name) . '_keep" value="' . e($val) . '">';
                break;
            case 'teeth':
                $set = array_filter(array_map('trim', explode(',', $val)));
                $arch = static function (string $title, array $nums) use ($name, $set): string {
                    $h = '<div class="d-teeth-arch"><span class="d-teeth-lab">' . e($title) . '</span><div class="d-teeth-row">';
                    foreach ($nums as $n) {
                        $on = in_array((string) $n, $set, true);
                        $h .= '<label class="d-tooth' . ($on ? ' is-on' : '') . '"><input type="checkbox" name="' . e($name) . '[]" value="' . $n . '"' . ($on ? ' checked' : '') . '><span>' . $n . '</span></label>';
                    }
                    return $h . '</div></div>';
                };
                $in = '<div class="d-teeth" role="group" aria-label="' . $label . '">'
                    . $arch('Upper right', [18, 17, 16, 15, 14, 13, 12, 11])
                    . $arch('Upper left', [21, 22, 23, 24, 25, 26, 27, 28])
                    . $arch('Lower left', [31, 32, 33, 34, 35, 36, 37, 38])
                    . $arch('Lower right', [48, 47, 46, 45, 44, 43, 42, 41])
                    . '</div>';
                $label = '<span>' . $label . '</span>';
                return '<div class="d-field' . $wide . '"><div class="d-label">' . $label . $star . '</div>' . $in . $help . '</div>';
            default:
                $attrs = '';
                if ($type === 'number') {
                    $attrs = ' step="' . e((string) ($f['step'] ?? '1')) . '"' . (isset($f['min']) ? ' min="' . e((string) $f['min']) . '"' : '');
                }
                $in = '<input id="' . $id . '" type="' . e(in_array($type, ['email', 'date', 'time', 'password', 'number', 'datetime-local', 'tel', 'url'], true) ? $type : 'text') . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . $attrs . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '') . (!empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '') . (!empty($f['autocomplete']) ? ' autocomplete="' . e($f['autocomplete']) . '"' : '') . '>';
        }
        return '<div class="d-field' . $wide . '"><label for="' . $id . '">' . $label . $star . '</label>' . $in . $help . '</div>';
    }

    /**
     * Reads and checks the posted values for the given fields.
     * @param array<string,array<string,mixed>> $fields
     * @return array{0:array<string,mixed>,1:?string} [data, error]
     */
    public static function collect(array $fields, string $uploadDir = 'uploads/site'): array
    {
        $data = [];
        foreach ($fields as $name => $f) {
            $type = $f['type'] ?? 'text';
            $raw = $_POST[$name] ?? null;
            $label = (string) ($f['label'] ?? $name);
            switch ($type) {
                case 'toggle':
                    $data[$name] = ($raw === '1' || $raw === 1) ? 1 : 0;
                    break;
                case 'checks':
                    $vals = is_array($raw) ? array_values(array_intersect(array_map('strval', $raw), array_map('strval', array_keys($f['options'])))) : [];
                    $data[$name] = implode(',', $vals);
                    break;
                case 'teeth':
                    $allow = ['11','12','13','14','15','16','17','18','21','22','23','24','25','26','27','28','31','32','33','34','35','36','37','38','41','42','43','44','45','46','47','48'];
                    $vals = is_array($raw) ? array_values(array_intersect(array_map('strval', $raw), $allow)) : [];
                    $data[$name] = implode(',', $vals);
                    if ($data[$name] === '' && !empty($f['required'])) {
                        return [$data, $label . ' is required.'];
                    }
                    break;
                case 'number':
                    $s = trim((string) $raw);
                    if ($s === '') {
                        if (!empty($f['required'])) {
                            return [$data, $label . ' is required.'];
                        }
                        $data[$name] = array_key_exists('null', $f) && $f['null'] ? null : ($f['default'] ?? 0);
                    } elseif (!is_numeric($s) || (isset($f['min']) && (float) $s < (float) $f['min'])) {
                        return [$data, $label . ' must be a number' . (isset($f['min']) ? ' of ' . $f['min'] . ' or more' : '') . '.'];
                    } else {
                        $data[$name] = $s + 0;
                    }
                    break;
                case 'image':
                    $keep = (string) ($_POST[$name . '_keep'] ?? '');
                    $up = $_FILES[$name] ?? null;
                    if ($up && ($up['error'] ?? 4) === UPLOAD_ERR_OK) {
                        [$path, $err] = self::saveImage($up, $uploadDir);
                        if ($err !== null) {
                            return [$data, $label . ': ' . $err];
                        }
                        $data[$name] = $path;
                    } else {
                        $data[$name] = str_starts_with($keep, 'uploads/') ? $keep : null;
                    }
                    break;
                case 'select':
                    $s = (string) $raw;
                    if ($s !== '' && !array_key_exists($s, $f['options'])) {
                        return [$data, $label . ' has an invalid choice.'];
                    }
                    if ($s === '' && !empty($f['required'])) {
                        return [$data, $label . ' is required.'];
                    }
                    $data[$name] = $s === '' ? null : $s;
                    break;
                default:
                    $s = trim((string) $raw);
                    if ($s === '' && !empty($f['required'])) {
                        return [$data, $label . ' is required.'];
                    }
                    if (isset($f['max']) && mb_strlen($s) > (int) $f['max']) {
                        return [$data, $label . ' is too long (max ' . (int) $f['max'] . ' characters).'];
                    }
                    if ($type === 'email' && $s !== '' && !filter_var($s, FILTER_VALIDATE_EMAIL)) {
                        return [$data, $label . ' is not a valid email.'];
                    }
                    if (in_array($type, ['date'], true) && $s !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                        return [$data, $label . ' is not a valid date.'];
                    }
                    $data[$name] = $s === '' ? (array_key_exists('null', $f) && $f['null'] ? null : '') : $s;
            }
        }
        return [$data, null];
    }

    /** @return array{0:?string,1:?string} [path, error] */
    public static function saveImage(array $up, string $dir): array
    {
        if (($up['size'] ?? 0) > 2 * 1024 * 1024) {
            return [null, 'The image is larger than 2 MB.'];
        }
        $info = @getimagesize((string) $up['tmp_name']);
        $mime = $info['mime'] ?? '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if ($ext === null) {
            return [null, 'Please upload a JPG, PNG or WebP image.'];
        }
        $root = dirname(__DIR__) . '/public/' . $dir;
        if (!is_dir($root) && !@mkdir($root, 0775, true)) {
            return [null, 'Could not save the image.'];
        }
        $name = bin2hex(random_bytes(10)) . '.' . $ext;
        if (!move_uploaded_file((string) $up['tmp_name'], $root . '/' . $name)) {
            return [null, 'Could not save the image.'];
        }
        return [$dir . '/' . $name, null];
    }

    /** Saves the posted form. Returns an error text or null. */
    public function save(): ?string
    {
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id ? $this->find($id) : null;
        if ($id && $existing === null) {
            return 'That item was not found.';
        }
        [$data, $err] = self::collect($this->m['fields'], 'uploads/' . ($this->m['upload_dir'] ?? 'site'));
        if ($err !== null) {
            keep_old($_POST);
            return $err;
        }
        if (isset($this->m['before_save'])) {
            $r = $this->m['before_save']($data, $existing);
            if (is_string($r)) {
                keep_old($_POST);
                return $r;
            }
            $data = $r;
        }
        if ($id) {
            $this->db->update($this->m['table'], $data, 'id = ?', [$id]);
        } else {
            foreach ($this->m['scope'] ?? [] as $c => $v) {
                $data[$c] = $v;
            }
            $id = $this->db->insert($this->m['table'], $data);
        }
        if (isset($this->m['after_save'])) {
            $this->m['after_save']($id, $data);
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }
        if (isset($this->m['before_delete']) && ($msg = $this->m['before_delete']($row)) !== null) {
            flash($msg, 'bad');
            return false;
        }
        $this->db->run('DELETE FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        return true;
    }
}
