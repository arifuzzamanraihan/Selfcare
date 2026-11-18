<?php
// Selfcare original config: hospital work lists. Wards and beds (bed board), inpatient admissions (admit, transfer, discharge with bed charges),
// emergency triage queue and operation theatre bookings. Loaded by core/Ops.php with the other files in this folder; format is at the top of core/Ops.php.
// The OPD token queue (clinic.php) is shared with hospitals. Doctors, nurses, lab, pharmacy and billing use the standard portal modules.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;
$wardTypes = ['General ward' => 'General ward', 'Private room' => 'Private room', 'Semi-private' => 'Semi-private', 'ICU' => 'ICU', 'CCU' => 'CCU', 'NICU' => 'NICU', 'PICU' => 'PICU', 'HDU' => 'High dependency (HDU)', 'Maternity' => 'Maternity / labour', 'Paediatric' => 'Paediatric ward', 'Isolation' => 'Isolation', 'Emergency' => 'Emergency observation', 'Day care' => 'Day care'];

/** Beds that can be given to a patient now, plus the one the record already has. */
$freeBeds = static function (Selfcare\Portal $p, ?array $row, bool $all): array {
    $rows = app()->db->all("SELECT code, title, cat, amount, status FROM sc_records WHERE tenant_id = ? AND module = 'beds' AND deleted_at IS NULL AND (status = 'available' OR code = ?) ORDER BY title, code", [$p->tenantId, (string) ($row['cat'] ?? '')]);
    $o = [];
    foreach ($rows as $r) {
        $o[(string) $r['code']] = trim($r['code'] . ' · ' . $r['title'] . ' · ' . $r['cat'] . ($r['status'] !== 'available' ? ' (current)' : ''));
    }
    return $o;
};

return [
    'beds' => [
        'types' => ['hospital'], 'title' => 'Wards and beds', 'singular' => 'bed', 'icon' => 'bed', 'group' => 'Hospital',
        'perm' => 'manage_appointments', 'prefix' => '', 'code_required' => true, 'sub' => 'Every bed with its ward, daily charge and state. Free, occupied, being cleaned or under repair.',
        'fields' => [
            'code' => ['label' => 'Bed number', 'required' => true, 'max' => 40, 'placeholder' => 'ICU-01'],
            'title' => ['label' => 'Ward or room', 'required' => true, 'max' => 190, 'placeholder' => 'Medical ICU'],
            'cat' => ['label' => 'Kind of bed', 'type' => 'select', 'options' => $wardTypes, 'required' => true, 'default' => 'General ward'],
            'place_a' => ['label' => 'Floor / block', 'max' => 190],
            'amount' => ['label' => 'Charge per day', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['available' => 'Free', 'occupied' => 'Occupied', 'cleaning' => 'Being cleaned', 'maintenance' => 'Under repair'], 'required' => true, 'default' => 'available'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Bed', 'title' => 'Ward', 'cat' => 'Kind', 'place_a' => 'Floor', 'amount' => ['Per day', 'money'], 'status' => 'State'],
        'status' => ['available' => ['Free', 'ok'], 'occupied' => ['Occupied', 'info'], 'cleaning' => ['Being cleaned', 'warn'], 'maintenance' => ['Under repair', 'mute']], 'initial' => 'available', 'status_field' => true,
        'flow' => [
            'clean' => ['Ready for next patient', 'cleaning', 'available', 'check'],
            'repair' => ['Send for repair', ['available', 'cleaning'], 'maintenance', 'settings', 'confirm' => 'Take this bed out of use?'],
            'fixed' => ['Repair done', 'maintenance', 'available', 'check'],
        ],
        'search' => ['code', 'title', 'cat', 'place_a'], 'sort' => 'code', 'dir' => 'asc',
        'kpis' => [
            ['Free beds', "SELECT COUNT(*) " . $rec('beds') . " AND status = 'available'", 'check', 'ready for a patient', 'beds', ['f_status' => 'available']],
            ['Occupied', "SELECT COUNT(*) " . $rec('beds') . " AND status = 'occupied'", 'user', 'with a patient', 'beds', ['f_status' => 'occupied']],
            ['Being cleaned', "SELECT COUNT(*) " . $rec('beds') . " AND status = 'cleaning'", 'clock', 'after discharge', 'beds', ['f_status' => 'cleaning']],
        ],
        'charts' => [['title' => 'Beds by state', 'kind' => 'status']],
    ],
    'admissions' => [
        'types' => ['hospital'], 'title' => 'Admissions (IPD)', 'singular' => 'admission', 'icon' => 'users', 'group' => 'Hospital',
        'perm' => 'manage_appointments', 'prefix' => 'ADM-', 'sub' => 'Patients in the wards: book a bed, admit, move to another bed or ward, discharge and bill the stay.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'New patient (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'staff_id' => ['label' => 'Admitting doctor', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Not assigned'],
            'cat' => ['label' => 'Bed', 'type' => 'select', 'options' => 'beds_free', 'required' => true, 'help' => 'Only free beds are listed. Add beds under Wards and beds.'],
            'tag' => ['label' => 'Kind of admission', 'type' => 'select', 'options' => ['Planned' => 'Planned', 'Emergency' => 'From emergency', 'Referred' => 'Referred', 'Delivery' => 'Delivery / maternity', 'Day care' => 'Day care'], 'default' => 'Planned'],
            'date1' => $date('Admission date', ['default' => date('Y-m-d'), 'required' => true]),
            'party' => ['label' => 'Insurance / panel', 'max' => 190, 'placeholder' => 'Self pay, company or insurance name'],
            'place_b' => ['label' => 'Advance deposit', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Diagnosis and reason for admission', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'No', 'title' => 'Patient', 'cat' => 'Bed', 'party' => 'Panel', 'date1' => ['Admitted', 'date'], 'date2' => ['Discharged', 'date'], 'place_b' => ['Deposit', 'num'], 'amount' => ['Stay charges', 'money'], 'status' => 'State'],
        'status' => ['booked' => ['Bed booked', 'warn'], 'admitted' => ['In hospital', 'info'], 'discharged' => ['Discharged', 'ok'], 'cancelled' => ['Cancelled', 'mute']], 'initial' => 'booked',
        'dynamic' => ['beds_free' => $freeBeds],
        'flow' => [
            'admit' => ['Admit now', 'booked', 'admitted', 'check', 'asset' => ['module' => 'beds', 'col' => 'cat', 'need' => 'available', 'to' => 'occupied']],
            'transfer' => ['Move to another bed', 'admitted', 'admitted', 'send', 'ask' => ['cat' => ['label' => 'New bed', 'type' => 'select', 'options' => 'beds_free', 'required' => true], 'notes' => ['label' => 'Reason for the move', 'type' => 'textarea', 'rows' => 2, 'max' => 300, 'wide' => true]],
                'asset' => ['module' => 'beds', 'col' => 'cat', 'need' => 'available', 'to' => 'occupied', 'release' => 'cleaning']],
            'discharge' => ['Discharge', 'admitted', 'discharged', 'check', 'confirm' => 'Discharge this patient? The bed goes to cleaning and the stay is charged.', 'set' => ['date2' => 'today'], 'calc' => 'bed_days',
                'asset' => ['module' => 'beds', 'col' => 'cat', 'to' => 'cleaning']],
            'cancel' => ['Cancel booking', 'booked', 'cancelled', 'x', 'confirm' => 'Cancel this booking?'],
        ],
        'intake' => ['title', 'phone', 'notes'],
        'bill' => ['type' => 'ipd_final_bill', 'when' => ['admitted', 'discharged'], 'item' => '{cat} bed charges, {qty} day(s)', 'price' => 'amount', 'x' => ['admission_no' => 'code']],
        'print' => ['label' => 'Print admission slip', 'title' => 'Admission slip', 'when' => ['booked', 'admitted', 'discharged'], 'rows' => ['code' => 'Admission no', 'title' => 'Patient', 'cat' => 'Bed', 'party' => 'Panel', 'date1' => 'Admitted', 'date2' => 'Discharged'], 'notes' => true, 'sign' => ['Admitting doctor', 'Patient / guardian']],
        'search' => ['code', 'title', 'cat', 'party', 'phone'],
        'kpis' => [
            ['In hospital now', "SELECT COUNT(*) " . $rec('admissions') . " AND status = 'admitted'", 'user', 'admitted patients', 'admissions', ['f_status' => 'admitted']],
            ['Admitted today', "SELECT COUNT(*) " . $rec('admissions') . " AND date1 = CURDATE() AND status IN ('admitted','discharged')", 'plus', 'new admissions', 'admissions', []],
            ['Discharged today', "SELECT COUNT(*) " . $rec('admissions') . " AND date2 = CURDATE() AND status = 'discharged'", 'check', 'went home', 'admissions', ['f_status' => 'discharged']],
        ],
        'charts' => [['title' => 'Admissions by state', 'kind' => 'status'], ['title' => 'Admissions per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('admissions') . ' AND date1 >= {since} GROUP BY m']],
    ],
    'emergency' => [
        'types' => ['hospital'], 'title' => 'Emergency', 'singular' => 'emergency case', 'icon' => 'heart', 'group' => 'Hospital',
        'perm' => 'manage_appointments', 'prefix' => '', 'number' => 'daily', 'queue' => true, 'call' => 'treat', 'board' => ['now' => ['in_treatment'], 'wait' => ['waiting']],
        'sub' => 'Triage board. The sickest patient goes first: drag a case up or use the arrows. Numbers are given automatically each day.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Unknown / new (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190, 'placeholder' => 'Unknown male if not known'],
            'phone' => ['label' => 'Phone of a relative', 'type' => 'tel', 'max' => 40],
            'tag' => ['label' => 'Triage level', 'type' => 'select', 'options' => ['Red' => 'Red: immediate', 'Orange' => 'Orange: very urgent', 'Yellow' => 'Yellow: urgent', 'Green' => 'Green: standard', 'Blue' => 'Blue: not urgent'], 'required' => true, 'default' => 'Yellow'],
            'cat' => ['label' => 'Arrived by', 'type' => 'select', 'options' => ['Walk-in' => 'Walk-in', 'Ambulance' => 'Ambulance', 'Referred' => 'Referred from another place', 'Police' => 'Police case'], 'default' => 'Walk-in'],
            'staff_id' => ['label' => 'Doctor on duty', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Not assigned'],
            'date1' => $date('Date', ['default' => date('Y-m-d'), 'required' => true]),
            'notes' => ['label' => 'Complaint, vitals and first findings', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'No', 'tag' => 'Triage', 'title' => 'Patient', 'cat' => 'Arrived by', 'status' => 'State'],
        'status' => ['waiting' => ['Waiting', 'warn'], 'in_treatment' => ['In treatment', 'info'], 'admitted' => ['Admitted', 'ok'], 'discharged' => ['Treated and sent home', 'ok'], 'referred' => ['Referred', 'mute'], 'cancelled' => ['Left / cancelled', 'mute']], 'initial' => 'waiting',
        'flow' => [
            'treat' => ['Start treatment', 'waiting', 'in_treatment', 'play'],
            'admit' => ['Send to admission', 'in_treatment', 'admitted', 'send', 'confirm' => 'Mark as admitted? Then book a bed under Admissions.'],
            'discharge' => ['Send home', 'in_treatment', 'discharged', 'check'],
            'refer' => ['Refer to another place', ['waiting', 'in_treatment'], 'referred', 'send', 'ask' => ['notes' => ['label' => 'Where and why', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true]]],
            'cancel' => ['Left without treatment', ['waiting'], 'cancelled', 'x', 'confirm' => 'Mark this patient as left?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'opd_bill', 'when' => ['in_treatment', 'admitted', 'discharged', 'referred'], 'item' => 'Emergency care ({tag})', 'price' => 'amount', 'x' => []],
        'print' => ['label' => 'Print emergency slip', 'title' => 'Emergency slip', 'when' => ['waiting', 'in_treatment', 'admitted', 'discharged', 'referred'], 'rows' => ['code' => 'No', 'title' => 'Patient', 'tag' => 'Triage', 'cat' => 'Arrived by', 'date1' => 'Date'], 'notes' => true, 'sign' => false],
        'search' => ['code', 'title', 'phone', 'tag'],
        'kpis' => [
            ['Waiting now', "SELECT COUNT(*) " . $rec('emergency') . " AND date1 = CURDATE() AND status = 'waiting'", 'clock', 'in today\'s emergency queue', 'emergency', ['f_status' => 'waiting']],
            ['Red and orange waiting', "SELECT COUNT(*) " . $rec('emergency') . " AND date1 = CURDATE() AND status = 'waiting' AND tag IN ('Red','Orange')", 'heart', 'need a doctor first', 'emergency', ['f_status' => 'waiting']],
            ['In treatment', "SELECT COUNT(*) " . $rec('emergency') . " AND date1 = CURDATE() AND status = 'in_treatment'", 'user', 'with a doctor now', 'emergency', ['f_status' => 'in_treatment']],
        ],
        'charts' => [['title' => 'Emergency cases by state today', 'kind' => 'status']],
    ],
    'theatre' => [
        'types' => ['hospital'], 'title' => 'Operation theatre', 'singular' => 'operation', 'icon' => 'cal', 'group' => 'Hospital',
        'perm' => 'manage_appointments', 'prefix' => 'OT-', 'slot' => ['cols' => ['cat', 'date1', 'place_b'], 'status' => ['scheduled', 'in_progress'], 'msg' => 'That theatre is already booked at that time. Choose another time or theatre.'],
        'sub' => 'Book operations by theatre and time. A theatre cannot be booked twice at the same time.',
        'fields' => [
            'person_id' => ['label' => 'Registered patient', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'New patient (type the name below)'],
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'place_a' => ['label' => 'Operation / procedure', 'required' => true, 'max' => 190, 'placeholder' => 'Appendicectomy'],
            'staff_id' => ['label' => 'Surgeon', 'type' => 'select', 'options' => 'doctors', 'blank_label' => 'Not assigned'],
            'party' => ['label' => 'Anaesthetist and team', 'max' => 190],
            'cat' => ['label' => 'Theatre', 'type' => 'select', 'options' => ['OT-1' => 'OT-1', 'OT-2' => 'OT-2', 'OT-3' => 'OT-3', 'Minor OT' => 'Minor OT', 'Labour OT' => 'Labour / caesarean OT', 'Cath lab' => 'Cath lab'], 'required' => true, 'default' => 'OT-1'],
            'date1' => $date('Date', ['default' => date('Y-m-d'), 'required' => true]),
            'place_b' => ['label' => 'Start time', 'type' => 'time', 'required' => true, 'default' => '09:00'],
            'qty' => ['label' => 'Expected hours', 'type' => 'number', 'step' => '0.25', 'min' => 0, 'default' => 1],
            'amount' => ['label' => 'Procedure fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Pre-operation notes (fasting, consent, blood, allergies)', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'No', 'date1' => ['Date', 'date'], 'place_b' => 'Time', 'cat' => 'Theatre', 'title' => 'Patient', 'place_a' => 'Operation', 'status' => 'State'],
        'status' => ['scheduled' => ['Scheduled', 'warn'], 'in_progress' => ['In theatre', 'info'], 'done' => ['Done', 'ok'], 'postponed' => ['Postponed', 'mute'], 'cancelled' => ['Cancelled', 'mute']], 'initial' => 'scheduled',
        'flow' => [
            'start' => ['Start operation', 'scheduled', 'in_progress', 'play'],
            'finish' => ['Operation done', 'in_progress', 'done', 'check', 'ask' => ['notes' => ['label' => 'Operation notes and findings', 'type' => 'textarea', 'rows' => 4, 'required' => true, 'max' => 2000, 'wide' => true]]],
            'postpone' => ['Postpone', 'scheduled', 'postponed', 'clock', 'ask' => ['notes' => ['label' => 'Why is it postponed?', 'type' => 'textarea', 'rows' => 2, 'required' => true, 'max' => 300, 'wide' => true]]],
            'reschedule' => ['Book again', 'postponed', 'scheduled', 'undo'],
            'cancel' => ['Cancel', ['scheduled', 'postponed'], 'cancelled', 'x', 'confirm' => 'Cancel this operation?'],
        ],
        'bill' => ['type' => 'procedure_bill', 'when' => ['done'], 'item' => '{place_a}', 'price' => 'amount', 'x' => []],
        'print' => ['label' => 'Print OT slip', 'title' => 'Operation slip', 'when' => ['scheduled', 'in_progress', 'done'], 'rows' => ['code' => 'No', 'title' => 'Patient', 'place_a' => 'Operation', 'cat' => 'Theatre', 'date1' => 'Date', 'place_b' => 'Time'], 'notes' => true, 'sign' => ['Surgeon', 'Anaesthetist']],
        'search' => ['code', 'title', 'place_a', 'cat'], 'sort' => 'date1', 'dir' => 'asc',
        'kpis' => [
            ['Operations today', "SELECT COUNT(*) " . $rec('theatre') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'booked for today', 'theatre', []],
            ['In theatre now', "SELECT COUNT(*) " . $rec('theatre') . " AND status = 'in_progress'", 'play', 'operations running', 'theatre', ['f_status' => 'in_progress']],
        ],
        'charts' => [['title' => 'Operations by state', 'kind' => 'status']],
    ],
];
