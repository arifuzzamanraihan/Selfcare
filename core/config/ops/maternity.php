<?php
// Selfcare original config: work lists for maternity and child-care centres (ANC visits, vaccines, deliveries). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'anc' => [
        'types' => ['maternity-center'],
        'title' => 'Antenatal visits', 'singular' => 'ANC visit', 'icon' => 'heart', 'group' => 'Maternity',
        'perm' => 'manage_prescriptions', 'prefix' => 'ANC-', 'queue' => true,
        'sub' => 'Today’s antenatal clinic. Record gestation, blood pressure and weight, then set the next visit.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Walk-in (type the name below)'],
            'title' => ['label' => 'Mother’s name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Doctor or midwife', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Attended by (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Visit', 'type' => 'select', 'options' => ['Booking' => 'Booking / first visit', 'ANC 2' => '2nd visit', 'ANC 3' => '3rd visit', 'ANC 4' => '4th visit', 'ANC 5+' => 'Later visit', '36 weeks' => '36-week visit', 'Term' => 'Term / 40-week visit'], 'default' => 'Booking'],
            'date1' => $date('Visit date', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Next visit due'),
            'ga' => ['label' => 'Gestation (weeks)', 'store' => 'meta', 'max' => 10],
            'bp' => ['label' => 'Blood pressure', 'store' => 'meta', 'max' => 20, 'placeholder' => '110/70'],
            'weight' => ['label' => 'Weight (kg)', 'store' => 'meta', 'max' => 10],
            'fhr' => ['label' => 'Fetal heart rate', 'store' => 'meta', 'max' => 20],
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Findings and advice', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Visit', 'title' => 'Mother', 'cat' => 'Visit type', 'party' => 'Attended by', 'date1' => ['Date', 'date'], 'date2' => ['Next due', 'date'], 'status' => 'State'],
        'status' => ['booked' => ['Booked', 'warn'], 'seen' => ['Seen', 'ok'], 'missed' => ['Missed', 'bad'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'booked',
        'flow' => [
            'see' => ['Record visit', 'booked', 'seen', 'check', 'ask' => ['ga' => ['label' => 'Gestation (weeks)', 'max' => 10], 'bp' => ['label' => 'Blood pressure', 'max' => 20], 'weight' => ['label' => 'Weight (kg)', 'max' => 10], 'fhr' => ['label' => 'Fetal heart rate', 'max' => 20], 'date2' => ['label' => 'Next visit due', 'type' => 'date', 'null' => true], 'notes' => ['label' => 'Findings and advice', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true], 'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'miss' => ['Mark missed', 'booked', 'missed', 'x'],
            'cancel' => ['Cancel', 'booked', 'cancelled', 'x', 'confirm' => 'Cancel this visit?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['seen'], 'item' => 'Antenatal visit ({cat})', 'price' => 'amount', 'x' => ['doctor' => 'party']],
        'print' => ['label' => 'Print visit note', 'title' => 'Antenatal visit record', 'when' => ['seen'], 'rows' => ['code' => 'Visit no', 'title' => 'Mother', 'cat' => 'Visit', 'party' => 'Attended by', 'date1' => 'Date', 'date2' => 'Next visit', 'ga' => 'Gestation (weeks)', 'bp' => 'Blood pressure', 'weight' => 'Weight (kg)', 'fhr' => 'Fetal heart rate', 'amount' => 'Fee'], 'notes_label' => 'Findings'],
        'search' => ['code', 'title', 'phone', 'cat', 'party'],
        'kpis' => [
            ['ANC visits today', "SELECT COUNT(*) " . $rec('anc') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'heart', 'on the list', 'anc', []],
            ['Waiting to be seen', "SELECT COUNT(*) " . $rec('anc') . " AND date1 = CURDATE() AND status = 'booked'", 'clock', 'not seen yet', 'anc', ['f_status' => 'booked']],
        ],
        'charts' => [['title' => 'ANC visits by state', 'kind' => 'status'], ['title' => 'ANC visits per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('anc') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"]],
        'reports' => ['anc_register' => ['label' => 'Antenatal visit register', 'desc' => 'ANC visits in the period.', 'date' => 'date1']],
    ],
    'vaccines' => [
        'types' => ['maternity-center'],
        'title' => 'Vaccinations', 'singular' => 'vaccination', 'icon' => 'check', 'group' => 'Maternity',
        'perm' => 'manage_prescriptions', 'prefix' => 'VAC-',
        'sub' => 'Childhood and maternal immunisations (EPI and TT). Mark given and set the next due date.',
        'fields' => [
            'title' => ['label' => 'Name', 'required' => true, 'max' => 190, 'help' => 'Child or mother.'],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Vaccine', 'type' => 'select', 'options' => ['BCG' => 'BCG', 'OPV' => 'OPV / polio', 'Penta' => 'Pentavalent', 'PCV' => 'PCV', 'MR' => 'Measles / rubella', 'TT' => 'Tetanus toxoid (mother)', 'Hepatitis B' => 'Hepatitis B', 'Other' => 'Other'], 'required' => true],
            'qty' => ['label' => 'Dose number', 'type' => 'number', 'step' => '1', 'min' => 1, 'default' => 1],
            'date1' => $date('Given or due on', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Next dose due'),
            'party' => ['label' => 'Given by', 'max' => 190],
            'notes' => ['label' => 'Batch and notes', 'type' => 'textarea', 'rows' => 2, 'max' => 500, 'wide' => true],
        ],
        'columns' => ['code' => 'No', 'title' => 'Name', 'cat' => 'Vaccine', 'qty' => ['Dose', 'num'], 'date1' => ['Date', 'date'], 'date2' => ['Next due', 'date'], 'status' => 'State'],
        'status' => ['due' => ['Due', 'warn'], 'given' => ['Given', 'ok'], 'missed' => ['Missed', 'bad']],
        'initial' => 'due',
        'flow' => [
            'give' => ['Mark given', 'due', 'given', 'check', 'ask' => ['date1' => ['label' => 'Given on', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')], 'party' => ['label' => 'Given by', 'max' => 190], 'date2' => ['label' => 'Next dose due', 'type' => 'date', 'null' => true], 'notes' => ['label' => 'Batch and notes', 'type' => 'textarea', 'rows' => 2, 'max' => 500, 'wide' => true]]],
            'miss' => ['Mark missed', 'due', 'missed', 'x'],
        ],
        'print' => ['label' => 'Print record', 'title' => 'Immunisation record', 'when' => ['given', 'due'], 'rows' => ['code' => 'Number', 'title' => 'Name', 'cat' => 'Vaccine', 'qty' => 'Dose', 'date1' => 'Date', 'date2' => 'Next due', 'party' => 'Given by', 'status' => 'State'], 'notes_label' => 'Batch'],
        'search' => ['code', 'title', 'cat', 'phone'],
        'kpis' => [['Doses due', "SELECT COUNT(*) " . $rec('vaccines') . " AND status = 'due' AND date1 <= CURDATE()", 'clock', 'today or overdue', 'vaccines', ['f_status' => 'due']]],
        'charts' => [['title' => 'Vaccinations by state', 'kind' => 'status']],
        'reports' => ['vaccine_register' => ['label' => 'Vaccination register', 'desc' => 'Immunisations in the period.', 'date' => 'date1']],
    ],
    'deliveries' => [
        'types' => ['maternity-center'],
        'title' => 'Deliveries', 'singular' => 'delivery', 'icon' => 'heart', 'group' => 'Maternity',
        'perm' => 'manage_prescriptions', 'prefix' => 'DEL-',
        'sub' => 'Labour and birth records, from admission to discharge.',
        'fields' => [
            'title' => ['label' => 'Mother’s name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Doctor or midwife', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Choose…'],
            'party' => ['label' => 'Attended by (if not on the list)', 'max' => 190],
            'cat' => ['label' => 'Type', 'type' => 'select', 'options' => ['Normal' => 'Normal vaginal', 'Assisted' => 'Assisted', 'C-section' => 'Caesarean'], 'default' => 'Normal'],
            'place_a' => ['label' => 'Outcome', 'type' => 'select', 'options' => ['Live birth' => 'Live birth', 'Stillbirth' => 'Stillbirth', 'Twin live' => 'Twins (live)', 'Other' => 'Other'], 'default' => 'Live birth'],
            'qty' => ['label' => 'Birth weight (kg)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'date1' => $date('Admission or birth date', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Discharge date'),
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Delivery notes', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'No', 'title' => 'Mother', 'cat' => 'Type', 'place_a' => 'Outcome', 'date1' => ['Date', 'date'], 'status' => 'State'],
        'status' => ['admitted' => ['Admitted', 'warn'], 'delivered' => ['Delivered', 'ok'], 'discharged' => ['Discharged', 'mute'], 'referred' => ['Referred', 'info']],
        'initial' => 'admitted',
        'flow' => [
            'deliver' => ['Record delivery', 'admitted', 'delivered', 'check', 'ask' => ['cat' => ['label' => 'Type', 'type' => 'select', 'options' => ['Normal' => 'Normal vaginal', 'Assisted' => 'Assisted', 'C-section' => 'Caesarean']], 'place_a' => ['label' => 'Outcome', 'type' => 'select', 'options' => ['Live birth' => 'Live birth', 'Stillbirth' => 'Stillbirth', 'Twin live' => 'Twins (live)', 'Other' => 'Other']], 'qty' => ['label' => 'Birth weight (kg)', 'type' => 'number', 'step' => '0.01', 'min' => 0], 'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true]]],
            'discharge' => ['Discharge', 'delivered', 'discharged', 'send', 'set' => ['date2' => 'today']],
            'refer' => ['Refer', ['admitted', 'delivered'], 'referred', 'send', 'ask' => ['notes' => ['label' => 'Referred to and reason', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true]]],
        ],
        'bill' => ['type' => 'procedure_bill', 'when' => ['delivered', 'discharged'], 'item' => '{cat} delivery', 'price' => 'amount', 'x' => ['procedure' => 'cat', 'surgeon' => 'party', 'procedure_date' => 'date1']],
        'print' => ['label' => 'Print record', 'title' => 'Delivery record', 'when' => ['delivered', 'discharged', 'referred'], 'rows' => ['code' => 'Number', 'title' => 'Mother', 'party' => 'Attended by', 'cat' => 'Type', 'place_a' => 'Outcome', 'qty' => 'Birth weight (kg)', 'date1' => 'Date', 'date2' => 'Discharged', 'status' => 'State'], 'notes_label' => 'Notes'],
        'search' => ['code', 'title', 'phone', 'party'],
        'kpis' => [['In labour / admitted', "SELECT COUNT(*) " . $rec('deliveries') . " AND status = 'admitted'", 'heart', 'not yet delivered', 'deliveries', ['f_status' => 'admitted']]],
        'charts' => [['title' => 'Deliveries by state', 'kind' => 'status']],
        'reports' => ['delivery_register' => ['label' => 'Delivery register', 'desc' => 'Births in the period with type and outcome.', 'date' => 'date1']],
    ],

];
