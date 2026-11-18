<?php
// Selfcare original config: work lists for imaging and radiology centres (and diagnostic centres that do studies). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'studies' => [
        'types' => ['imaging-center', 'diagnostic-center'],
        'title' => 'Imaging studies', 'singular' => 'study', 'icon' => 'file', 'group' => 'Imaging',
        'perm' => 'manage_prescriptions', 'prefix' => 'STU-', 'queue' => true,
        'sub' => 'From booking to a signed report. Drag today’s list to change the order. Enter the report and print it when it is ready.',
        'fields' => [
            'person_id' => ['label' => 'Registered client', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'party' => ['label' => 'Referred by', 'max' => 190],
            'staff_id' => ['label' => 'Radiologist', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Assign later'],
            'cat' => ['label' => 'Modality', 'type' => 'select', 'options' => ['X-ray' => 'X-ray', 'Ultrasound' => 'Ultrasound', 'CT' => 'CT', 'MRI' => 'MRI', 'Mammography' => 'Mammography', 'BMD' => 'Bone density (BMD)', 'OPG' => 'OPG / dental X-ray', 'Fluoroscopy' => 'Fluoroscopy', 'Other' => 'Other'], 'required' => true, 'default' => 'X-ray'],
            'place_a' => ['label' => 'Study / body part', 'required' => true, 'max' => 190, 'placeholder' => 'Chest PA, USG abdomen, MRI brain…'],
            'date1' => $date('Study date', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Report and impression', 'type' => 'textarea', 'rows' => 7, 'max' => 5000, 'wide' => true, 'help' => 'This text is printed on the radiology report.'],
        ],
        'columns' => ['code' => 'Study', 'title' => 'Patient', 'cat' => 'Modality', 'place_a' => 'Study', 'date1' => ['Date', 'date'], 'date2' => ['Reported', 'date'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'in_progress' => ['In progress', 'info'], 'reported' => ['Reported', 'ok'], 'delivered' => ['Delivered', 'mute'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'start' => ['Start study', 'booked', 'in_progress', 'play'],
            'report' => ['Enter report', ['booked', 'in_progress'], 'reported', 'check', 'ask' => ['notes' => ['label' => 'Report and impression', 'type' => 'textarea', 'rows' => 8, 'required' => true, 'max' => 5000, 'wide' => true]], 'set' => ['date2' => 'today']],
            'deliver' => ['Mark delivered', 'reported', 'delivered', 'send'],
            'cancel' => ['Cancel', ['booked', 'in_progress'], 'cancelled', 'x', 'confirm' => 'Cancel this study?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'imaging_invoice', 'when' => ['booked', 'in_progress', 'reported', 'delivered'], 'item' => '{cat} — {place_a}', 'price' => 'amount', 'x' => ['referred_by' => 'party']],
        'print' => ['label' => 'Print report', 'title' => 'Radiology report', 'when' => ['reported', 'delivered'], 'rows' => ['code' => 'Report no', 'title' => 'Patient', 'phone' => 'Phone', 'party' => 'Referred by', 'cat' => 'Modality', 'place_a' => 'Study', 'date1' => 'Study date', 'date2' => 'Reported on'], 'notes_label' => 'Findings and impression', 'sign_label' => 'Radiologist'],
        'search' => ['code', 'title', 'cat', 'place_a', 'phone', 'party'],
        'kpis' => [
            ['Studies today', "SELECT COUNT(*) " . $rec('studies') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'file', 'on the list', 'studies', []],
            ['Waiting for a report', "SELECT COUNT(*) " . $rec('studies') . " AND status IN ('booked','in_progress')", 'clock', 'not reported yet', 'studies', []],
            ['Ready to hand over', "SELECT COUNT(*) " . $rec('studies') . " AND status = 'reported'", 'check', 'reported, not delivered', 'studies', ['f_status' => 'reported']],
        ],
        'charts' => [['title' => 'Studies by state', 'kind' => 'status'], ['title' => 'Studies per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('studies') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['study_register' => ['label' => 'Imaging study register', 'desc' => 'Studies in the period with modality, turnaround and state.', 'date' => 'date1']],
    ],

];
