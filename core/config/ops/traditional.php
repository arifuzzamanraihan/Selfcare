<?php
// Selfcare original config: work lists for traditional and alternative medicine clinics. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'consults' => [
        'types' => ['alternative-medicine'],
        'title' => 'Consultations', 'singular' => 'consultation', 'icon' => 'cal', 'group' => 'Clinic',
        'perm' => 'manage_prescriptions', 'prefix' => 'CON-', 'queue' => true,
        'sub' => 'Today’s consultations. Record the system of medicine, findings and the remedy, then print the advice.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Practitioner', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Practitioner name (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'System', 'type' => 'select', 'options' => ['Unani' => 'Unani', 'Ayurveda' => 'Ayurveda', 'Homeopathy' => 'Homeopathy', 'Herbal' => 'Herbal', 'Other' => 'Other'], 'default' => 'Unani'],
            'date1' => $date('Consultation date', ['default' => date('Y-m-d'), 'required' => true]),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Findings and remedy', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true, 'help' => 'Printed for the patient.'],
        ],
        'columns' => ['code' => 'Consult', 'title' => 'Patient', 'cat' => 'System', 'party' => 'Practitioner', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'seen' => ['Seen', 'ok'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'see' => ['Complete consult', 'booked', 'seen', 'check', 'ask' => ['notes' => ['label' => 'Findings and remedy', 'type' => 'textarea', 'rows' => 6, 'required' => true, 'max' => 3000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'cancel' => ['Cancel', 'booked', 'cancelled', 'x', 'confirm' => 'Cancel this consultation?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['seen'], 'item' => '{cat} consultation', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print advice', 'title' => 'Consultation and remedy', 'when' => ['seen'], 'rows' => ['code' => 'Number', 'title' => 'Patient', 'party' => 'Practitioner', 'cat' => 'System', 'date1' => 'Date', 'amount' => 'Fee'], 'notes_label' => 'Findings and remedy'],
        'search' => ['code', 'title', 'party', 'phone', 'cat'],
        'kpis' => [
            ['Consults today', "SELECT COUNT(*) " . $rec('consults') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'on the list', 'consults', []],
            ['Waiting', "SELECT COUNT(*) " . $rec('consults') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not seen yet', 'consults', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'Consultations by state', 'kind' => 'status']],
        'reports' => ['consult_register' => ['label' => 'Consultation register', 'desc' => 'Traditional medicine consultations in the period.', 'date' => 'date1']],
    ],
    'remedies' => [
        'types' => ['alternative-medicine'],
        'title' => 'Remedy list', 'singular' => 'remedy', 'icon' => 'box', 'group' => 'Clinic',
        'perm' => 'manage_prescriptions', 'prefix' => '',
        'sub' => 'Preparations and herbs you commonly prescribe, with a usual price.',
        'fields' => [
            'title' => ['label' => 'Remedy name', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Form', 'type' => 'select', 'options' => ['Decoction' => 'Decoction', 'Powder' => 'Powder', 'Oil' => 'Oil', 'Tablet' => 'Tablet / pill', 'Syrup' => 'Syrup', 'Other' => 'Other'], 'default' => 'Tablet'],
            'amount' => ['label' => 'Usual price', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'In use', 'inactive' => 'Not used now'], 'required' => true, 'default' => 'active'],
            'notes' => ['label' => 'How it is used', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['title' => 'Remedy', 'cat' => 'Form', 'amount' => ['Price', 'money'], 'status' => 'State'],
        'status' => ['active' => ['In use', 'ok'], 'inactive' => ['Not used', 'mute']], 'initial' => 'active', 'status_field' => true,
        'search' => ['title', 'cat'], 'sort' => 'title', 'dir' => 'asc',
    ],

];
