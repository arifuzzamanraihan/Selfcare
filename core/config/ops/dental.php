<?php
// Selfcare original config: work lists for dental clinics (treatment plans and tooth chart). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'txplans' => [
        'types' => ['dental-clinic'],
        'title' => 'Treatment plans', 'singular' => 'treatment plan', 'icon' => 'file', 'group' => 'Dental',
        'perm' => 'manage_prescriptions', 'prefix' => 'TX-', 'queue' => true,
        'sub' => 'Chart the teeth, write a plan the patient can accept, then work through it. Drag today’s plans to change the order.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Dentist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Dentist name (if not on the list)', 'max' => 190],
            'teeth' => ['label' => 'Teeth (FDI chart)', 'type' => 'teeth', 'store' => 'meta', 'wide' => true, 'help' => 'Tap the teeth this plan covers. Numbers follow the FDI two-digit system used in Bangladesh, Pakistan and India.'],
            'cat' => ['label' => 'Chief complaint', 'max' => 80, 'placeholder' => 'Pain, decay, cleaning, extraction…'],
            'date1' => $date('Plan date', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Next visit'),
            'amount' => ['label' => 'Estimated total', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Plan (work, tooth, fee)', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true, 'help' => 'One line per item, for example: “16 — RCT — 80”. This is printed for the patient to accept.'],
        ],
        'columns' => ['code' => 'Plan', 'title' => 'Patient', 'cat' => 'Complaint', 'party' => 'Dentist', 'date1' => ['Date', 'date'], 'amount' => ['Estimate', 'money'], 'status' => 'State'],
        'status' => ['proposed' => ['Proposed', 'warn'], 'accepted' => ['Accepted', 'info'], 'in_progress' => ['In progress', 'info'], 'completed' => ['Completed', 'ok'], 'declined' => ['Declined', 'mute']],
        'initial' => 'proposed',
        'flow' => [
            'accept' => ['Patient accepted', 'proposed', 'accepted', 'check'],
            'start' => ['Start treatment', ['proposed', 'accepted'], 'in_progress', 'play'],
            'complete' => ['Complete', ['accepted', 'in_progress'], 'completed', 'check'],
            'decline' => ['Patient declined', ['proposed', 'accepted'], 'declined', 'x', 'ask' => ['notes' => ['label' => 'Reason or alternative offered', 'type' => 'textarea', 'rows' => 3, 'max' => 500, 'wide' => true]]],
        ],
        'intake' => true,
        'bill' => ['type' => 'procedure_bill', 'when' => ['accepted', 'in_progress', 'completed'], 'item' => 'Dental treatment {code}', 'price' => 'amount', 'x' => ['procedure' => 'cat', 'surgeon' => 'party', 'procedure_date' => 'date1']],
        'print' => ['label' => 'Print plan', 'title' => 'Dental treatment plan', 'when' => ['proposed', 'accepted', 'in_progress', 'completed'], 'rows' => ['code' => 'Plan no', 'title' => 'Patient', 'party' => 'Dentist', 'cat' => 'Chief complaint', 'teeth' => 'Teeth', 'date1' => 'Date', 'date2' => 'Next visit', 'amount' => 'Estimated total', 'status' => 'State'], 'notes_label' => 'Planned work'],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [
            ['Plans waiting for acceptance', "SELECT COUNT(*) " . $rec('txplans') . " AND status = 'proposed'", 'file', 'shown to the patient', 'txplans', ['f_status' => 'proposed']],
            ['Treatment in progress', "SELECT COUNT(*) " . $rec('txplans') . " AND status IN ('accepted','in_progress')", 'clock', 'accepted or being done', 'txplans', []],
        ],
        'charts' => [['title' => 'Treatment plans by state', 'kind' => 'status']],
        'reports' => ['txplan_register' => ['label' => 'Treatment plan register', 'desc' => 'Dental treatment plans in the period.', 'date' => 'date1']],
    ],

];
