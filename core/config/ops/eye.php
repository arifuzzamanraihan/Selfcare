<?php
// Selfcare original config: work lists for eye clinics (exams, refraction and optical orders). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'eyexams' => [
        'types' => ['eye-clinic'],
        'title' => 'Eye exams', 'singular' => 'eye exam', 'icon' => 'eye', 'group' => 'Eye clinic',
        'perm' => 'manage_prescriptions', 'prefix' => 'EX-', 'queue' => true,
        'sub' => 'Today’s refraction and examination list. Record vision, refraction and IOP, then print a glasses prescription or send an optical order.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Optometrist or doctor', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Examiner name (if not on the list)', 'max' => 190],
            'date1' => $date('Exam date', ['default' => date('Y-m-d'), 'required' => true]),
            'va_r' => ['label' => 'VA right (distance)', 'store' => 'meta', 'max' => 20, 'placeholder' => '6/9 or 20/30'],
            'va_l' => ['label' => 'VA left (distance)', 'store' => 'meta', 'max' => 20],
            'sph_r' => ['label' => 'Sph right', 'store' => 'meta', 'max' => 12, 'placeholder' => '-1.25'],
            'cyl_r' => ['label' => 'Cyl right', 'store' => 'meta', 'max' => 12],
            'axis_r' => ['label' => 'Axis right', 'store' => 'meta', 'max' => 8, 'placeholder' => '90'],
            'add_r' => ['label' => 'Add right', 'store' => 'meta', 'max' => 12],
            'sph_l' => ['label' => 'Sph left', 'store' => 'meta', 'max' => 12],
            'cyl_l' => ['label' => 'Cyl left', 'store' => 'meta', 'max' => 12],
            'axis_l' => ['label' => 'Axis left', 'store' => 'meta', 'max' => 8],
            'add_l' => ['label' => 'Add left', 'store' => 'meta', 'max' => 12],
            'iop_r' => ['label' => 'IOP right (mmHg)', 'store' => 'meta', 'max' => 8],
            'iop_l' => ['label' => 'IOP left (mmHg)', 'store' => 'meta', 'max' => 8],
            'amount' => ['label' => 'Consultation fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Findings and advice', 'type' => 'textarea', 'rows' => 4, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Exam', 'title' => 'Patient', 'party' => 'Examiner', 'date1' => ['Date', 'date'], 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'in_exam' => ['In exam', 'info'], 'completed' => ['Completed', 'ok'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'start' => ['Start exam', 'booked', 'in_exam', 'play'],
            'complete' => ['Complete', ['booked', 'in_exam'], 'completed', 'check'],
            'cancel' => ['Cancel', ['booked', 'in_exam'], 'cancelled', 'x', 'confirm' => 'Cancel this exam?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['in_exam', 'completed'], 'item' => 'Eye examination', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print glasses Rx', 'title' => 'Spectacle prescription', 'when' => ['completed'], 'rows' => ['code' => 'Rx no', 'title' => 'Patient', 'party' => 'Examiner', 'date1' => 'Date', 'va_r' => 'VA right', 'va_l' => 'VA left', 'sph_r' => 'Sph right', 'cyl_r' => 'Cyl right', 'axis_r' => 'Axis right', 'add_r' => 'Add right', 'sph_l' => 'Sph left', 'cyl_l' => 'Cyl left', 'axis_l' => 'Axis left', 'add_l' => 'Add left', 'iop_r' => 'IOP right', 'iop_l' => 'IOP left'], 'notes_label' => 'Advice'],
        'search' => ['code', 'title', 'party', 'phone'],
        'kpis' => [
            ['Exams today', "SELECT COUNT(*) " . $rec('eyexams') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'eye', 'on the list', 'eyexams', []],
            ['Waiting', "SELECT COUNT(*) " . $rec('eyexams') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not started', 'eyexams', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'Eye exams by state', 'kind' => 'status']],
        'reports' => ['eyexam_register' => ['label' => 'Eye exam register', 'desc' => 'Eye examinations in the period.', 'date' => 'date1']],
    ],
    'optical' => [
        'types' => ['eye-clinic'],
        'title' => 'Optical orders', 'singular' => 'optical order', 'icon' => 'box', 'group' => 'Eye clinic',
        'perm' => 'manage_appointments', 'prefix' => 'OPT-',
        'sub' => 'Glasses and contact-lens jobs from the exam to the counter.',
        'fields' => [
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'party' => ['label' => 'Prescribed by', 'max' => 190],
            'cat' => ['label' => 'Lens type', 'type' => 'select', 'options' => ['Single vision' => 'Single vision', 'Bifocal' => 'Bifocal', 'Progressive' => 'Progressive', 'Contact lens' => 'Contact lens', 'Ready-made' => 'Ready-made'], 'default' => 'Single vision'],
            'place_a' => ['label' => 'Frame or brand', 'max' => 190],
            'date1' => $date('Order date', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Ready by'),
            'amount' => ['label' => 'Price', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Rx and coatings', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true, 'placeholder' => 'Copy the refraction, coatings (anti-reflective, blue light…), PD, lab instructions.'],
        ],
        'columns' => ['code' => 'Order', 'title' => 'Patient', 'cat' => 'Lens', 'place_a' => 'Frame', 'date1' => ['Ordered', 'date'], 'date2' => ['Ready by', 'date'], 'amount' => ['Price', 'money'], 'status' => 'State'],
        'status' => ['ordered' => ['Ordered', 'warn'], 'in_lab' => ['At the lab', 'info'], 'ready' => ['Ready', 'ok'], 'delivered' => ['Delivered', 'mute'], 'returned' => ['Returned', 'bad']],
        'initial' => 'ordered',
        'flow' => [
            'lab' => ['Send to lab', 'ordered', 'in_lab', 'send'],
            'ready' => ['Mark ready', ['ordered', 'in_lab'], 'ready', 'check'],
            'deliver' => ['Deliver', 'ready', 'delivered', 'check'],
            'return' => ['Return / remake', ['ready', 'delivered'], 'returned', 'undo', 'ask' => ['notes' => ['label' => 'Why is it returned?', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true]]],
        ],
        'bill' => ['type' => 'procedure_bill', 'when' => ['ordered', 'in_lab', 'ready', 'delivered'], 'item' => '{cat} {place_a}', 'price' => 'amount', 'x' => ['procedure' => 'cat']],
        'print' => ['label' => 'Print job order', 'title' => 'Optical job order', 'when' => ['ordered', 'in_lab', 'ready', 'delivered'], 'rows' => ['code' => 'Order', 'title' => 'Patient', 'phone' => 'Phone', 'party' => 'Prescribed by', 'cat' => 'Lens type', 'place_a' => 'Frame', 'date1' => 'Order date', 'date2' => 'Ready by', 'amount' => 'Price', 'status' => 'State'], 'notes_label' => 'Rx and instructions'],
        'search' => ['code', 'title', 'phone', 'cat', 'place_a'],
        'kpis' => [
            ['Jobs at the lab', "SELECT COUNT(*) " . $rec('optical') . " AND status IN ('ordered','in_lab')", 'box', 'not ready yet', 'optical', []],
            ['Ready to collect', "SELECT COUNT(*) " . $rec('optical') . " AND status = 'ready'", 'check', 'waiting at the counter', 'optical', ['f_status' => 'ready']],
        ],
        'charts' => [['title' => 'Optical orders by state', 'kind' => 'status']],
        'reports' => ['optical_register' => ['label' => 'Optical order register', 'desc' => 'Glasses and contact-lens jobs in the period.', 'date' => 'date1']],
    ],

];
