<?php
// Selfcare original config: work lists for home care and nursing (caregivers and visits). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

$careOptions = static function (\Selfcare\Portal $p, ?array $row = null, bool $onlyFree = false): array {
    $rows = \Selfcare\app()->db->all("SELECT code, title, status FROM sc_records WHERE tenant_id = ? AND module = 'caregivers' AND deleted_at IS NULL AND status <> 'inactive' ORDER BY title", [$p->tenantId]);
    $o = [];
    foreach ($rows as $r) {
        if ($onlyFree && $r['status'] !== 'available' && ($row === null || (string) $row['cat'] !== (string) $r['code'])) {
            continue;
        }
        $o[(string) $r['code']] = ($r['code'] ? $r['code'] . ' · ' : '') . $r['title'] . ($r['status'] !== 'available' ? ' (' . $r['status'] . ')' : '');
    }
    return $o;
};

return [

    'caregivers' => [
        'types' => ['home-care'],
        'title' => 'Caregivers', 'singular' => 'caregiver', 'icon' => 'users', 'group' => 'Home care',
        'perm' => 'manage_appointments', 'prefix' => 'CG-',
        'sub' => 'Nurses and caregivers on your roster, the areas they cover, and whether they are free.',
        'fields' => [
            'code' => ['label' => 'Short code', 'max' => 40, 'placeholder' => 'CG-01', 'help' => 'Leave empty and a number is assigned.'],
            'title' => ['label' => 'Name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Role', 'type' => 'select', 'options' => ['Nurse' => 'Nurse', 'Caregiver' => 'Caregiver', 'Therapist' => 'Therapist', 'Other' => 'Other'], 'default' => 'Caregiver'],
            'place_a' => ['label' => 'Areas covered', 'max' => 190],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['available' => 'Available', 'on_visit' => 'On a visit', 'leave' => 'On leave', 'inactive' => 'Not active'], 'required' => true, 'default' => 'available'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Code', 'title' => 'Name', 'cat' => 'Role', 'phone' => 'Phone', 'place_a' => 'Areas', 'status' => 'State'],
        'status' => ['available' => ['Available', 'ok'], 'on_visit' => ['On a visit', 'info'], 'leave' => ['On leave', 'warn'], 'inactive' => ['Not active', 'mute']],
        'initial' => 'available', 'status_field' => true,
        'search' => ['code', 'title', 'phone', 'cat', 'place_a'], 'sort' => 'title', 'dir' => 'asc',
        'kpis' => [['Caregivers free now', "SELECT COUNT(*) " . $rec('caregivers') . " AND status = 'available'", 'users', 'ready to be assigned', 'caregivers', ['f_status' => 'available']]],
        'charts' => [['title' => 'Caregivers by state', 'kind' => 'status']],
    ],
    'hcvisits' => [
        'types' => ['home-care'],
        'title' => 'Home visits', 'singular' => 'visit', 'icon' => 'cal', 'group' => 'Home care',
        'perm' => 'manage_appointments', 'prefix' => 'HV-', 'queue' => true,
        'sub' => 'Today’s visit list. Assign a free caregiver, record the visit, then bill.',
        'fields' => [
            'person_id' => ['label' => 'Registered client', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Client name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'place_a' => ['label' => 'Address', 'required' => true, 'max' => 190, 'wide' => true],
            'cat' => ['label' => 'Caregiver', 'type' => 'select', 'options' => 'caregivers_free', 'blank_label' => 'Assign when starting'],
            'party' => ['label' => 'Caregiver name (if not on the roster)', 'max' => 190],
            'date1' => $date('Visit date', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Next visit'),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Visit log', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Visit', 'title' => 'Client', 'cat' => 'Caregiver', 'place_a' => 'Address', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['scheduled' => ['Scheduled', 'warn'], 'in_progress' => ['In progress', 'info'], 'completed' => ['Completed', 'ok'], 'missed' => ['Missed', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'scheduled',
        'flow' => [
            'start' => ['Start visit', 'scheduled', 'in_progress', 'play', 'ask' => ['cat' => ['label' => 'Caregiver', 'type' => 'select', 'options' => 'caregivers_free', 'required' => true]], 'asset' => ['module' => 'caregivers', 'col' => 'cat', 'need' => 'available', 'to' => 'on_visit']],
            'complete' => ['Complete', ['scheduled', 'in_progress'], 'completed', 'check', 'ask' => ['notes' => ['label' => 'Visit log', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true], 'date2' => ['label' => 'Next visit', 'type' => 'date', 'null' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]], 'asset' => ['module' => 'caregivers', 'col' => 'cat', 'to' => 'available']],
            'miss' => ['Mark missed', 'scheduled', 'missed', 'x'],
            'cancel' => ['Cancel', ['scheduled', 'in_progress'], 'cancelled', 'x', 'confirm' => 'Cancel this visit?', 'asset' => ['module' => 'caregivers', 'col' => 'cat', 'to' => 'available']],
        ],
        'intake' => true,
        'bill' => ['type' => 'visit_invoice', 'when' => ['completed'], 'item' => 'Home visit {code}', 'price' => 'amount'],
        'print' => ['label' => 'Print visit log', 'title' => 'Home visit record', 'when' => ['completed', 'in_progress'], 'rows' => ['code' => 'Visit', 'title' => 'Client', 'phone' => 'Phone', 'place_a' => 'Address', 'cat' => 'Caregiver', 'party' => 'Caregiver name', 'date1' => 'Date', 'date2' => 'Next visit', 'amount' => 'Fee'], 'notes_label' => 'Visit log'],
        'search' => ['code', 'title', 'phone', 'place_a', 'cat', 'party'],
        'dynamic' => ['caregivers_free' => $careOptions],
        'kpis' => [
            ['Visits today', "SELECT COUNT(*) " . $rec('hcvisits') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'hcvisits', []],
            ['In progress', "SELECT COUNT(*) " . $rec('hcvisits') . " AND status = 'in_progress'", 'clock', 'out with a caregiver', 'hcvisits', ['f_status' => 'in_progress']],
        ],
        'charts' => [['title' => 'Visits by state', 'kind' => 'status'], ['title' => 'Visits per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('hcvisits') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['visit_log_register' => ['label' => 'Home visit register', 'desc' => 'Home visits in the period.', 'date' => 'date1']],
    ],

];
