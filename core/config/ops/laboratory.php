<?php
// Selfcare original config: work lists for laboratory. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [
    'samples' => [
        'types' => ['laboratory', 'diagnostic-center'], 'title' => 'Samples', 'singular' => 'sample', 'icon' => 'flask', 'group' => 'Laboratory',
        'perm' => 'manage_prescriptions', 'prefix' => 'SMP-', 'print' => true, 'sub' => 'Every sample from collection to a delivered report.',
        'fields' => [
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Test or panel', 'required' => true, 'max' => 80, 'placeholder' => 'CBC, lipid profile, urine R/E'],
            'place_a' => ['label' => 'Sample type', 'type' => 'select', 'options' => ['Blood' => 'Blood', 'Urine' => 'Urine', 'Stool' => 'Stool', 'Swab' => 'Swab', 'Sputum' => 'Sputum', 'Tissue' => 'Tissue', 'Other' => 'Other'], 'default' => 'Blood'],
            'party' => ['label' => 'Referred by', 'max' => 190],
            'date1' => $date('Collected on', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Result and remarks', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true, 'help' => 'This text is printed on the report.'],
        ],
        'columns' => ['code' => 'Sample', 'title' => 'Patient', 'cat' => 'Test', 'place_a' => 'Type', 'date1' => ['Collected', 'date'], 'date2' => ['Reported', 'date'], 'status' => 'State'],
        'status' => ['collected' => ['Collected', 'warn'], 'processing' => ['In process', 'info'], 'reported' => ['Result ready', 'ok'], 'delivered' => ['Delivered', 'mute'], 'rejected' => ['Rejected', 'bad']], 'initial' => 'collected',
        'flow' => [
            'start' => ['Start testing', 'collected', 'processing', 'play'],
            'report' => ['Enter result', ['collected', 'processing'], 'reported', 'check', 'ask' => ['notes' => ['label' => 'Result and remarks', 'type' => 'textarea', 'rows' => 6, 'required' => true, 'max' => 3000, 'wide' => true]], 'set' => ['date2' => 'today']],
            'deliver' => ['Mark delivered', 'reported', 'delivered', 'send'],
            'reject' => ['Reject sample', ['collected', 'processing'], 'rejected', 'x', 'ask' => ['notes' => ['label' => 'Why is it rejected?', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true, 'placeholder' => 'Haemolysed, too little, wrong tube…']]],
        ],
        'search' => ['code', 'title', 'cat', 'phone', 'party'],
        'kpis' => [
            ['Samples today', "SELECT COUNT(*) " . $rec('samples') . " AND date1 = CURDATE()", 'flask', 'collected today', 'samples', []],
            ['Waiting for a result', "SELECT COUNT(*) " . $rec('samples') . " AND status IN ('collected','processing')", 'clock', 'not reported yet', 'samples', ['f_status' => 'processing']],
            ['Ready to hand over', "SELECT COUNT(*) " . $rec('samples') . " AND status = 'reported'", 'check', 'reported, not delivered', 'samples', ['f_status' => 'reported']],
        ],
        'charts' => [['title' => 'Samples per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('samples') . ' AND date1 >= {since} GROUP BY m'], ['title' => 'Samples by state', 'kind' => 'status']],
    ],
];
