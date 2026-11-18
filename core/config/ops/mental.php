<?php
// Selfcare original config: work lists for mental health centres (session notes and treatment plans). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'mhsessions' => [
        'types' => ['mental-health'],
        'title' => 'Sessions', 'singular' => 'session', 'icon' => 'cal', 'group' => 'Mental health',
        'perm' => 'manage_prescriptions', 'prefix' => 'MHS-', 'queue' => true,
        'sub' => 'Today’s counselling and therapy list. Notes stay on this record and are only visible to people who can open this list.',
        'fields' => [
            'person_id' => ['label' => 'Registered client', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Client name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Counsellor or doctor', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Clinician name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Session type', 'type' => 'select', 'options' => ['Assessment' => 'Assessment', 'Counselling' => 'Counselling', 'Follow-up' => 'Follow-up', 'Family' => 'Family session', 'Group' => 'Group session'], 'default' => 'Counselling'],
            'date1' => $date('Session date', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Session notes', 'type' => 'textarea', 'rows' => 7, 'max' => 5000, 'wide' => true, 'help' => 'Clinical notes. Keep them factual. Only staff who can open this list can read them.'],
        ],
        'columns' => ['code' => 'Session', 'title' => 'Client', 'cat' => 'Type', 'party' => 'Clinician', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'in_session' => ['In session', 'info'], 'completed' => ['Completed', 'ok'], 'no_show' => ['Did not attend', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'start' => ['Start session', 'booked', 'in_session', 'play'],
            'complete' => ['Complete', ['booked', 'in_session'], 'completed', 'check', 'ask' => ['notes' => ['label' => 'Session notes', 'type' => 'textarea', 'rows' => 8, 'max' => 5000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'noshow' => ['Did not attend', 'booked', 'no_show', 'x'],
            'cancel' => ['Cancel', ['booked', 'in_session'], 'cancelled', 'x', 'confirm' => 'Cancel this session?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['completed'], 'item' => '{cat} session', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print notes', 'title' => 'Session notes', 'when' => ['completed'], 'rows' => ['code' => 'Session', 'title' => 'Client', 'party' => 'Clinician', 'cat' => 'Type', 'date1' => 'Date'], 'notes_label' => 'Notes', 'sign_label' => 'Clinician'],
        'search' => ['code', 'title', 'party', 'phone', 'cat'],
        'kpis' => [
            ['Sessions today', "SELECT COUNT(*) " . $rec('mhsessions') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'mhsessions', []],
            ['Waiting', "SELECT COUNT(*) " . $rec('mhsessions') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not started', 'mhsessions', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'Sessions by state', 'kind' => 'status']],
        'reports' => ['mh_session_register' => ['label' => 'Session register', 'desc' => 'Mental health sessions in the period (names and types only; notes are not listed).', 'date' => 'date1', 'cols' => ['date1' => ['Date', 'date'], 'code' => ['Session', 'text'], 'title' => ['Client', 'text'], 'cat' => ['Type', 'text'], 'party' => ['Clinician', 'text'], 'status' => ['State', 'status'], 'amount' => ['Fee', 'money']]]],
    ],
    'mhplans' => [
        'types' => ['mental-health'],
        'title' => 'Treatment plans', 'singular' => 'treatment plan', 'icon' => 'file', 'group' => 'Mental health',
        'perm' => 'manage_prescriptions', 'prefix' => 'MHP-',
        'sub' => 'Goals and the planned course of care, reviewed on a date you set.',
        'fields' => [
            'title' => ['label' => 'Client name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Clinician', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Clinician name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Focus', 'max' => 80, 'placeholder' => 'Anxiety, depression, family…'],
            'date1' => $date('Started on', ['default' => date('Y-m-d')]),
            'date2' => $date('Review on'),
            'notes' => ['label' => 'Goals and plan', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Plan', 'title' => 'Client', 'cat' => 'Focus', 'party' => 'Clinician', 'date1' => ['Started', 'date'], 'date2' => ['Review', 'date'], 'status' => 'State'],
        'status' => ['active' => ['Active', 'ok'], 'completed' => ['Completed', 'mute'], 'discontinued' => ['Discontinued', 'bad']],
        'initial' => 'active',
        'flow' => [
            'complete' => ['Complete', 'active', 'completed', 'check'],
            'stop' => ['Discontinue', 'active', 'discontinued', 'x', 'ask' => ['notes' => ['label' => 'Why is it stopped?', 'type' => 'textarea', 'rows' => 3, 'max' => 500, 'wide' => true]]],
        ],
        'print' => ['label' => 'Print plan', 'title' => 'Treatment plan', 'when' => ['active', 'completed'], 'rows' => ['code' => 'Plan', 'title' => 'Client', 'party' => 'Clinician', 'cat' => 'Focus', 'date1' => 'Started', 'date2' => 'Review', 'status' => 'State'], 'notes_label' => 'Goals and plan'],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [['Active plans', "SELECT COUNT(*) " . $rec('mhplans') . " AND status = 'active'", 'file', 'in progress', 'mhplans', ['f_status' => 'active']]],
        'reports' => ['mh_plan_register' => ['label' => 'Treatment plan register', 'desc' => 'Mental health treatment plans in the period.', 'date' => 'date1']],
    ],

];
