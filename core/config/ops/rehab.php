<?php
// Selfcare original config: work lists for rehabilitation and physiotherapy centres. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'rhplans' => [
        'types' => ['rehabilitation'],
        'title' => 'Therapy plans', 'singular' => 'therapy plan', 'icon' => 'file', 'group' => 'Therapy',
        'perm' => 'manage_prescriptions', 'prefix' => 'RHP-',
        'sub' => 'Goals and the course of therapy, reviewed on a date you set.',
        'fields' => [
            'person_id' => ['label' => 'Registered client', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Client name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Therapist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Therapist name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Program', 'type' => 'select', 'options' => ['Physiotherapy' => 'Physiotherapy', 'Occupational therapy' => 'Occupational therapy', 'Speech therapy' => 'Speech therapy', 'Other' => 'Other'], 'default' => 'Physiotherapy'],
            'date1' => $date('Started on', ['default' => date('Y-m-d')]),
            'date2' => $date('Review on'),
            'qty' => ['label' => 'Sessions planned', 'type' => 'number', 'step' => '1', 'min' => 0, 'default' => 0],
            'amount' => ['label' => 'Package fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Goals and plan', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Plan', 'title' => 'Client', 'cat' => 'Program', 'party' => 'Therapist', 'qty' => ['Sessions', 'num'], 'date1' => ['Started', 'date'], 'status' => 'State'],
        'status' => ['assessment' => ['Assessment', 'warn'], 'active' => ['Active', 'ok'], 'completed' => ['Completed', 'mute'], 'discontinued' => ['Discontinued', 'bad']],
        'initial' => 'assessment',
        'flow' => [
            'start' => ['Start plan', 'assessment', 'active', 'play'],
            'complete' => ['Complete', 'active', 'completed', 'check'],
            'stop' => ['Discontinue', ['assessment', 'active'], 'discontinued', 'x', 'ask' => ['notes' => ['label' => 'Why is it stopped?', 'type' => 'textarea', 'rows' => 3, 'max' => 500, 'wide' => true]]],
        ],
        'bill' => ['type' => 'package_bill', 'when' => ['assessment', 'active', 'completed'], 'item' => '{cat} package ({qty} sessions)', 'price' => 'amount'],
        'print' => ['label' => 'Print plan', 'title' => 'Therapy plan', 'when' => ['assessment', 'active', 'completed'], 'rows' => ['code' => 'Plan', 'title' => 'Client', 'party' => 'Therapist', 'cat' => 'Program', 'qty' => 'Sessions planned', 'date1' => 'Started', 'date2' => 'Review', 'amount' => 'Package fee', 'status' => 'State'], 'notes_label' => 'Goals'],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [['Active therapy plans', "SELECT COUNT(*) " . $rec('rhplans') . " AND status = 'active'", 'file', 'in progress', 'rhplans', ['f_status' => 'active']]],
        'reports' => ['rehab_plan_register' => ['label' => 'Therapy plan register', 'desc' => 'Therapy plans in the period.', 'date' => 'date1']],
    ],
    'rhsessions' => [
        'types' => ['rehabilitation'],
        'title' => 'Therapy sessions', 'singular' => 'session', 'icon' => 'cal', 'group' => 'Therapy',
        'perm' => 'manage_prescriptions', 'prefix' => 'RHS-', 'queue' => true,
        'sub' => 'Today’s session list. Record progress and pain, then bill a session or a package.',
        'fields' => [
            'title' => ['label' => 'Client name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Therapist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Therapist name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Therapy', 'type' => 'select', 'options' => ['Physiotherapy' => 'Physiotherapy', 'Occupational therapy' => 'Occupational therapy', 'Speech therapy' => 'Speech therapy', 'Other' => 'Other'], 'default' => 'Physiotherapy'],
            'date1' => $date('Session date', ['default' => date('Y-m-d'), 'required' => true]),
            'qty' => ['label' => 'Session number', 'type' => 'number', 'step' => '1', 'min' => 0, 'default' => 0],
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Progress, pain (0–10) and home exercises', 'type' => 'textarea', 'rows' => 5, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Session', 'title' => 'Client', 'cat' => 'Therapy', 'party' => 'Therapist', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'done' => ['Done', 'ok'], 'missed' => ['Missed', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'complete' => ['Record session', 'booked', 'done', 'check', 'ask' => ['notes' => ['label' => 'Progress, pain and home exercises', 'type' => 'textarea', 'rows' => 5, 'max' => 2000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'miss' => ['Mark missed', 'booked', 'missed', 'x'],
            'cancel' => ['Cancel', 'booked', 'cancelled', 'x', 'confirm' => 'Cancel this session?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'session_invoice', 'when' => ['done'], 'item' => '{cat} session {code}', 'price' => 'amount', 'x' => ['therapist' => 'party', 'sessions' => 'qty']],
        'print' => ['label' => 'Print note', 'title' => 'Therapy session note', 'when' => ['done'], 'rows' => ['code' => 'Session', 'title' => 'Client', 'party' => 'Therapist', 'cat' => 'Therapy', 'qty' => 'Session no', 'date1' => 'Date', 'amount' => 'Fee'], 'notes_label' => 'Progress'],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [
            ['Sessions today', "SELECT COUNT(*) " . $rec('rhsessions') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'rhsessions', []],
            ['Waiting', "SELECT COUNT(*) " . $rec('rhsessions') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not recorded yet', 'rhsessions', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'Sessions by state', 'kind' => 'status'], ['title' => 'Sessions per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('rhsessions') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['rehab_session_register' => ['label' => 'Therapy session register', 'desc' => 'Therapy sessions in the period.', 'date' => 'date1']],
    ],
    'rhpacks' => [
        'types' => ['rehabilitation'],
        'title' => 'Session packages', 'singular' => 'package', 'icon' => 'box', 'group' => 'Therapy',
        'perm' => 'manage_appointments', 'prefix' => 'PKG-',
        'sub' => 'Bundles of sessions you sell (for example 10 physiotherapy visits).',
        'fields' => [
            'title' => ['label' => 'Package name', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Program', 'type' => 'select', 'options' => ['Physiotherapy' => 'Physiotherapy', 'Occupational therapy' => 'Occupational therapy', 'Speech therapy' => 'Speech therapy', 'Other' => 'Other'], 'default' => 'Physiotherapy'],
            'qty' => ['label' => 'Sessions included', 'type' => 'number', 'step' => '1', 'min' => 1, 'default' => 10],
            'amount' => ['label' => 'Price', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'Offered', 'inactive' => 'Not offered now'], 'required' => true, 'default' => 'active'],
            'notes' => ['label' => 'What is included', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Package', 'title' => 'Name', 'cat' => 'Program', 'qty' => ['Sessions', 'num'], 'amount' => ['Price', 'money'], 'status' => 'State'],
        'status' => ['active' => ['Offered', 'ok'], 'inactive' => ['Not offered', 'mute']], 'initial' => 'active', 'status_field' => true,
        'bill' => ['type' => 'package_bill', 'when' => ['active'], 'item' => '{title} ({qty} sessions)', 'price' => 'amount'],
        'search' => ['code', 'title', 'cat'], 'sort' => 'title', 'dir' => 'asc',
    ],

];
