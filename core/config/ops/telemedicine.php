<?php
// Selfcare original config: work lists for telemedicine providers (online consultations). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'tlsessions' => [
        'types' => ['telemedicine'],
        'title' => 'Consultations', 'singular' => 'consultation', 'icon' => 'cal', 'group' => 'Telemedicine',
        'perm' => 'manage_appointments', 'prefix' => 'TEL-', 'queue' => true,
        'sub' => 'Today’s online list. Paste a meeting link, take payment, then start and complete the call. Write a prescription from the usual Prescriptions page.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Specialist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Specialist name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Channel', 'type' => 'select', 'options' => ['Video' => 'Video', 'Audio' => 'Audio', 'Chat' => 'Chat'], 'default' => 'Video'],
            'place_a' => ['label' => 'Meeting link', 'max' => 190, 'placeholder' => 'https://…', 'help' => 'Paste the link the patient should open. We do not host the video ourselves.', 'wide' => true],
            'date1' => $date('Session date', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Consultation notes', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Session', 'title' => 'Patient', 'cat' => 'Channel', 'party' => 'Specialist', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'waiting' => ['Waiting / paid', 'info'], 'in_call' => ['In the call', 'info'], 'completed' => ['Completed', 'ok'], 'no_show' => ['Did not attend', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'ready' => ['Mark paid / waiting', 'booked', 'waiting', 'check'],
            'start' => ['Start call', ['booked', 'waiting'], 'in_call', 'play'],
            'complete' => ['Complete', ['waiting', 'in_call'], 'completed', 'check', 'ask' => ['notes' => ['label' => 'Consultation notes', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true]]],
            'noshow' => ['Did not attend', ['booked', 'waiting'], 'no_show', 'x'],
            'cancel' => ['Cancel', ['booked', 'waiting'], 'cancelled', 'x', 'confirm' => 'Cancel this consultation?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'consultation_invoice', 'when' => ['booked', 'waiting', 'in_call', 'completed'], 'item' => '{cat} consultation {code}', 'price' => 'amount', 'x' => ['consultant' => 'party', 'session_at' => 'date1', 'channel' => 'cat']],
        'print' => ['label' => 'Print summary', 'title' => 'Consultation summary', 'when' => ['completed'], 'rows' => ['code' => 'Session', 'title' => 'Patient', 'party' => 'Specialist', 'cat' => 'Channel', 'date1' => 'Date', 'place_a' => 'Meeting link', 'amount' => 'Fee'], 'notes_label' => 'Notes'],
        'search' => ['code', 'title', 'party', 'phone', 'cat'],
        'kpis' => [
            ['Consults today', "SELECT COUNT(*) " . $rec('tlsessions') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'tlsessions', []],
            ['Waiting to start', "SELECT COUNT(*) " . $rec('tlsessions') . " AND date1 = CURDATE() AND status IN ('booked','waiting')", 'clock', 'not in the call yet', 'tlsessions', []],
        ],
        'charts' => [['title' => 'Consultations by state', 'kind' => 'status'], ['title' => 'Consultations per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('tlsessions') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['telemed_register' => ['label' => 'Consultation register', 'desc' => 'Online consultations in the period.', 'date' => 'date1']],
    ],

];
