<?php
// Selfcare original config: work lists for dialysis centres (machines and sessions). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

$machineOptions = static function (\Selfcare\Portal $p, ?array $row = null, bool $onlyFree = false): array {
    $rows = \Selfcare\app()->db->all("SELECT code, title, status FROM sc_records WHERE tenant_id = ? AND module = 'dmachines' AND deleted_at IS NULL AND status <> 'retired' ORDER BY code", [$p->tenantId]);
    $o = [];
    foreach ($rows as $r) {
        if ($onlyFree && $r['status'] !== 'available' && ($row === null || (string) $row['cat'] !== (string) $r['code'])) {
            continue;
        }
        $o[(string) $r['code']] = $r['code'] . ($r['title'] ? ' · ' . $r['title'] : '') . ($r['status'] !== 'available' ? ' (' . $r['status'] . ')' : '');
    }
    return $o;
};

return [

    'dmachines' => [
        'types' => ['dialysis-center'],
        'title' => 'Machines', 'singular' => 'machine', 'icon' => 'settings', 'group' => 'Dialysis',
        'perm' => 'manage_appointments', 'prefix' => '', 'code_required' => true,
        'sub' => 'Your dialysis machines, where they stand and when they are free.',
        'fields' => [
            'code' => ['label' => 'Machine no', 'required' => true, 'max' => 40],
            'title' => ['label' => 'Make or model', 'max' => 190],
            'place_a' => ['label' => 'Station or room', 'max' => 190],
            'date1' => $date('Next service due'),
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['available' => 'Available', 'in_use' => 'In a session', 'maintenance' => 'In maintenance', 'retired' => 'Not used'], 'required' => true, 'default' => 'available'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Machine', 'title' => 'Model', 'place_a' => 'Station', 'date1' => ['Service due', 'date'], 'status' => 'State'],
        'status' => ['available' => ['Available', 'ok'], 'in_use' => ['In a session', 'info'], 'maintenance' => ['Maintenance', 'warn'], 'retired' => ['Not used', 'mute']],
        'initial' => 'available', 'status_field' => true,
        'flow' => [
            'workshop' => ['Send to maintenance', 'available', 'maintenance', 'settings', 'confirm' => 'This machine cannot be used until it is back.'],
            'ready' => ['Back in service', 'maintenance', 'available', 'check', 'ask' => ['date1' => ['label' => 'Next service due', 'type' => 'date', 'null' => true], 'notes' => ['label' => 'Work done', 'type' => 'textarea', 'rows' => 3, 'max' => 500, 'wide' => true]]],
        ],
        'search' => ['code', 'title', 'place_a'], 'sort' => 'code', 'dir' => 'asc',
        'kpis' => [
            ['Machines free now', "SELECT COUNT(*) " . $rec('dmachines') . " AND status = 'available'", 'check', 'ready for a session', 'dmachines', ['f_status' => 'available']],
        ],
        'charts' => [['title' => 'Machines by state', 'kind' => 'status']],
    ],
    'dsessions' => [
        'types' => ['dialysis-center'],
        'title' => 'Dialysis sessions', 'singular' => 'session', 'icon' => 'cal', 'group' => 'Dialysis',
        'perm' => 'manage_appointments', 'prefix' => 'DS-', 'queue' => true,
        'sub' => 'Today’s session list. Assign a free machine, record pre- and post-weights, then complete and bill.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'party' => ['label' => 'Nurse or technician', 'max' => 190],
            'cat' => ['label' => 'Machine', 'type' => 'select', 'options' => 'machines_free', 'blank_label' => 'Assign when starting', 'help' => 'Only free machines are listed.'],
            'date1' => $date('Session date', ['default' => date('Y-m-d'), 'required' => true]),
            'tag' => ['label' => 'Shift', 'type' => 'select', 'options' => ['Morning' => 'Morning', 'Afternoon' => 'Afternoon', 'Evening' => 'Evening'], 'default' => 'Morning'],
            'pre_wt' => ['label' => 'Pre-weight (kg)', 'store' => 'meta', 'max' => 10],
            'post_wt' => ['label' => 'Post-weight (kg)', 'store' => 'meta', 'max' => 10],
            'uf' => ['label' => 'Ultrafiltration (L)', 'store' => 'meta', 'max' => 10],
            'bp' => ['label' => 'Blood pressure', 'store' => 'meta', 'max' => 20, 'placeholder' => '130/80'],
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Session notes', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Session', 'title' => 'Patient', 'tag' => 'Shift', 'cat' => 'Machine', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['scheduled' => ['Scheduled', 'warn'], 'in_session' => ['In session', 'info'], 'completed' => ['Completed', 'ok'], 'missed' => ['Missed', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'scheduled',
        'flow' => [
            'start' => ['Start session', 'scheduled', 'in_session', 'play', 'ask' => ['cat' => ['label' => 'Machine', 'type' => 'select', 'options' => 'machines_free', 'required' => true], 'pre_wt' => ['label' => 'Pre-weight (kg)', 'max' => 10], 'bp' => ['label' => 'Blood pressure', 'max' => 20]], 'asset' => ['module' => 'dmachines', 'col' => 'cat', 'need' => 'available', 'to' => 'in_use']],
            'complete' => ['Complete', 'in_session', 'completed', 'check', 'ask' => ['post_wt' => ['label' => 'Post-weight (kg)', 'max' => 10], 'uf' => ['label' => 'Ultrafiltration (L)', 'max' => 10], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]], 'asset' => ['module' => 'dmachines', 'col' => 'cat', 'to' => 'available']],
            'miss' => ['Mark missed', 'scheduled', 'missed', 'x', 'confirm' => 'Mark this session as missed?'],
            'cancel' => ['Cancel', ['scheduled', 'in_session'], 'cancelled', 'x', 'confirm' => 'Cancel this session?', 'asset' => ['module' => 'dmachines', 'col' => 'cat', 'to' => 'available']],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['completed'], 'item' => 'Dialysis session {code}', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print session sheet', 'title' => 'Dialysis session record', 'when' => ['in_session', 'completed'], 'rows' => ['code' => 'Session', 'title' => 'Patient', 'party' => 'Nurse / technician', 'cat' => 'Machine', 'tag' => 'Shift', 'date1' => 'Date', 'pre_wt' => 'Pre-weight (kg)', 'post_wt' => 'Post-weight (kg)', 'uf' => 'UF (L)', 'bp' => 'Blood pressure', 'amount' => 'Fee'], 'notes_label' => 'Notes'],
        'search' => ['code', 'title', 'phone', 'cat', 'party', 'tag'],
        'dynamic' => ['machines_free' => $machineOptions],
        'kpis' => [
            ['Sessions today', "SELECT COUNT(*) " . $rec('dsessions') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'dsessions', []],
            ['In a chair now', "SELECT COUNT(*) " . $rec('dsessions') . " AND status = 'in_session'", 'clock', 'running', 'dsessions', ['f_status' => 'in_session']],
        ],
        'charts' => [['title' => 'Sessions by state', 'kind' => 'status'], ['title' => 'Sessions per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('dsessions') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['dialysis_register' => ['label' => 'Dialysis session register', 'desc' => 'Sessions in the period with machine, shift and state.', 'date' => 'date1']],
    ],

];
