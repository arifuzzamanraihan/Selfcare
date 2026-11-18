<?php
// Selfcare original config: work lists for specialty clinics (procedure records). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;


return [
    'procedures' => [
        'types' => ['specialty-clinic'],
        'title' => 'Procedures', 'singular' => 'procedure', 'icon' => 'file', 'group' => 'Clinic',
        'perm' => 'manage_prescriptions', 'prefix' => 'PR-', 'queue' => true,
        'sub' => 'Booked procedures for the day. Drag to change the order. Record what was done and make an invoice when it is finished.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Specialist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Specialist name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Procedure', 'required' => true, 'max' => 80, 'placeholder' => 'ECG, echo, endoscopy, dressing…'],
            'place_a' => ['label' => 'Room or theatre', 'max' => 190],
            'date1' => $date('Procedure date', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Findings and notes', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true, 'help' => 'Printed on the procedure note.'],
        ],
        'columns' => ['code' => 'No', 'title' => 'Patient', 'cat' => 'Procedure', 'party' => 'Specialist', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'in_progress' => ['In progress', 'info'], 'completed' => ['Completed', 'ok'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'start' => ['Start', 'booked', 'in_progress', 'play'],
            'complete' => ['Complete', ['booked', 'in_progress'], 'completed', 'check', 'ask' => ['notes' => ['label' => 'Findings and notes', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'cancel' => ['Cancel', ['booked', 'in_progress'], 'cancelled', 'x', 'confirm' => 'Cancel this procedure?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'procedure_bill', 'when' => ['in_progress', 'completed'], 'item' => '{cat}', 'price' => 'amount', 'x' => ['procedure' => 'cat', 'surgeon' => 'party', 'procedure_date' => 'date1']],
        'print' => ['label' => 'Print note', 'title' => 'Procedure note', 'when' => ['completed'], 'rows' => ['code' => 'Number', 'title' => 'Patient', 'phone' => 'Phone', 'party' => 'Specialist', 'cat' => 'Procedure', 'place_a' => 'Room', 'date1' => 'Date', 'amount' => 'Fee'], 'notes_label' => 'Findings'],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [
            ['Procedures today', "SELECT COUNT(*) " . $rec('procedures') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'file', 'booked for today', 'procedures', []],
            ['Waiting to start', "SELECT COUNT(*) " . $rec('procedures') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not started yet', 'procedures', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'Procedures by state', 'kind' => 'status'], ['title' => 'Procedures per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('procedures') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['procedure_register' => ['label' => 'Procedure register', 'desc' => 'Procedures in the period with specialist, fee and state.', 'date' => 'date1']],
    ],
];
