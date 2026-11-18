<?php
// Selfcare original config: work lists for clinic, hospital and other walk-in practices (token queue). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;


return [
    'tokens' => [
        'types' => ['clinic'],
        'title' => 'Token queue', 'singular' => 'token', 'icon' => 'cal', 'group' => 'Clinic',
        'perm' => 'manage_appointments', 'prefix' => '', 'number' => 'daily',
        'queue' => true, 'call' => 'call', 'board' => ['now' => ['called', 'in_consult'], 'wait' => ['waiting']],
        'sub' => 'Today’s patient serial. Numbers are issued automatically. Drag or use the arrows to change who is next, or press Alt+Up / Alt+Down.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Doctor', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Any / not assigned'],
            'party' => ['label' => 'Doctor name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Visit type', 'type' => 'select', 'options' => ['New' => 'New visit', 'Follow-up' => 'Follow-up', 'Report show' => 'Report show', 'Procedure' => 'Procedure / dressing'], 'default' => 'New'],
            'tag' => ['label' => 'Priority', 'type' => 'select', 'options' => ['Normal' => 'Normal', 'Urgent' => 'Urgent', 'Elderly' => 'Elderly / priority', 'Emergency' => 'Emergency'], 'default' => 'Normal'],
            'date1' => $date('Queue date', ['default' => date('Y-m-d'), 'required' => true]),
            'notes' => ['label' => 'Chief complaint or note', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Token', 'tag' => 'Priority', 'title' => 'Patient', 'party' => 'Doctor', 'cat' => 'Visit', 'status' => 'State'],
        'status' => ['waiting' => ['Waiting', 'warn'], 'called' => ['Called', 'info'], 'in_consult' => ['In consult', 'info'], 'done' => ['Done', 'ok'], 'skipped' => ['Skipped', 'mute'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'waiting',
        'flow' => [
            'call' => ['Call', 'waiting', 'called', 'send'],
            'start' => ['Start consult', ['waiting', 'called'], 'in_consult', 'play'],
            'finish' => ['Mark done', ['called', 'in_consult'], 'done', 'check'],
            'skip' => ['Skip', ['waiting', 'called'], 'skipped', 'x', 'confirm' => 'Skip this token? You can call them again from the skipped list by adding a new token.'],
            'cancel' => ['Cancel', ['waiting', 'called'], 'cancelled', 'x', 'confirm' => 'Cancel this token?'],
            'requeue' => ['Back to waiting', ['skipped', 'called'], 'waiting', 'undo'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['in_consult', 'done'], 'item' => '{cat} consultation', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print token', 'title' => 'Token', 'when' => ['waiting', 'called', 'in_consult', 'done'], 'rows' => ['code' => 'Token', 'title' => 'Patient', 'party' => 'Doctor', 'cat' => 'Visit type', 'tag' => 'Priority', 'date1' => 'Date'], 'notes' => false, 'sign' => false],
        'search' => ['code', 'title', 'party', 'phone', 'cat', 'tag'],
        'kpis' => [
            ['Waiting now', "SELECT COUNT(*) " . $rec('tokens') . " AND date1 = CURDATE() AND status = 'waiting'", 'clock', 'in today’s queue', 'tokens', ['f_status' => 'waiting']],
            ['Being seen', "SELECT COUNT(*) " . $rec('tokens') . " AND date1 = CURDATE() AND status IN ('called','in_consult')", 'cal', 'called or in consult', 'tokens', []],
            ['Seen today', "SELECT COUNT(*) " . $rec('tokens') . " AND date1 = CURDATE() AND status = 'done'", 'check', 'consults finished', 'tokens', ['f_status' => 'done']],
        ],
        'charts' => [['title' => 'Tokens by state today', 'kind' => 'status'], ['title' => 'Tokens per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('tokens') . ' AND date1 >= {since} GROUP BY m']],
        'reports' => ['token_register' => ['label' => 'Token register', 'desc' => 'Every token issued in the period, with doctor, visit type and state.', 'date' => 'date1', 'cols' => ['date1' => ['Date', 'date'], 'code' => ['Token', 'text'], 'title' => ['Patient', 'text'], 'party' => ['Doctor', 'text'], 'cat' => ['Visit', 'text'], 'tag' => ['Priority', 'text'], 'status' => ['State', 'status']]]],
    ],
];
