<?php
// Selfcare original config: work lists for nursing homes (residents, care plans, medication rounds, incidents). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'residents' => [
        'types' => ['nursing-home'],
        'title' => 'Residents', 'singular' => 'resident', 'icon' => 'users', 'group' => 'Care',
        'perm' => 'manage_appointments', 'prefix' => 'RES-',
        'sub' => 'People who live here or have enquired, with room, care level and next of kin.',
        'fields' => [
            'person_id' => ['label' => 'Registered person', 'type' => 'select', 'options' => 'patients', 'blank_label' => 'Not on the people list (type the name below)'],
            'title' => ['label' => 'Resident name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'party' => ['label' => 'Next of kin', 'max' => 190],
            'place_a' => ['label' => 'Room', 'max' => 40],
            'cat' => ['label' => 'Care level', 'type' => 'select', 'options' => ['Residential' => 'Residential', 'Assisted' => 'Assisted living', 'High' => 'High dependency', 'Respite' => 'Respite'], 'default' => 'Residential'],
            'date1' => $date('Admitted on'),
            'date2' => $date('Discharged on'),
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'ID', 'title' => 'Resident', 'place_a' => 'Room', 'cat' => 'Care level', 'party' => 'Next of kin', 'date1' => ['Admitted', 'date'], 'status' => 'State'],
        'status' => ['enquiry' => ['Enquiry', 'info'], 'admitted' => ['Admitted', 'ok'], 'on_leave' => ['On leave', 'warn'], 'discharged' => ['Discharged', 'mute']],
        'initial' => 'enquiry',
        'flow' => [
            'admit' => ['Admit', 'enquiry', 'admitted', 'check', 'ask' => ['place_a' => ['label' => 'Room', 'required' => true, 'max' => 40], 'date1' => ['label' => 'Admitted on', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')]]],
            'leave' => ['Mark on leave', 'admitted', 'on_leave', 'send'],
            'return' => ['Back from leave', 'on_leave', 'admitted', 'undo'],
            'discharge' => ['Discharge', ['admitted', 'on_leave'], 'discharged', 'check', 'set' => ['date2' => 'today'], 'ask' => ['notes' => ['label' => 'Discharge note', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true]]],
        ],
        'intake' => true,
        'bill' => ['type' => 'admission_bill', 'when' => ['admitted', 'on_leave', 'discharged'], 'item' => 'Residential care ({cat})', 'price' => 'amount'],
        'search' => ['code', 'title', 'place_a', 'party', 'phone'],
        'kpis' => [
            ['Residents now', "SELECT COUNT(*) " . $rec('residents') . " AND status IN ('admitted','on_leave')", 'users', 'living here', 'residents', []],
            ['Enquiries', "SELECT COUNT(*) " . $rec('residents') . " AND status = 'enquiry'", 'cal', 'not admitted yet', 'residents', ['f_status' => 'enquiry']],
        ],
        'charts' => [['title' => 'Residents by state', 'kind' => 'status']],
        'reports' => ['resident_register' => ['label' => 'Resident register', 'desc' => 'Residents and enquiries in the period (by admission date).', 'date' => 'date1']],
    ],
    'ncare' => [
        'types' => ['nursing-home'],
        'title' => 'Care plans', 'singular' => 'care plan', 'icon' => 'file', 'group' => 'Care',
        'perm' => 'manage_prescriptions', 'prefix' => 'CP-',
        'sub' => 'What support each resident needs, reviewed on a date you set.',
        'fields' => [
            'title' => ['label' => 'Resident name', 'required' => true, 'max' => 190],
            'party' => ['label' => 'Written by', 'max' => 190],
            'cat' => ['label' => 'Focus', 'max' => 80, 'placeholder' => 'Mobility, nutrition, skin, behaviour…'],
            'date1' => $date('Started on', ['default' => date('Y-m-d')]),
            'date2' => $date('Review on'),
            'notes' => ['label' => 'Plan', 'type' => 'textarea', 'rows' => 6, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Plan', 'title' => 'Resident', 'cat' => 'Focus', 'party' => 'Author', 'date1' => ['Started', 'date'], 'date2' => ['Review', 'date'], 'status' => 'State'],
        'status' => ['draft' => ['Draft', 'warn'], 'active' => ['Active', 'ok'], 'reviewed' => ['Reviewed', 'info'], 'closed' => ['Closed', 'mute']],
        'initial' => 'draft',
        'flow' => [
            'activate' => ['Make active', 'draft', 'active', 'check'],
            'review' => ['Record review', ['active', 'reviewed'], 'reviewed', 'check', 'ask' => ['date2' => ['label' => 'Next review', 'type' => 'date', 'null' => true], 'notes' => ['label' => 'Review notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true]]],
            'close' => ['Close', ['active', 'reviewed'], 'closed', 'x'],
        ],
        'print' => ['label' => 'Print plan', 'title' => 'Care plan', 'when' => ['draft', 'active', 'reviewed', 'closed'], 'rows' => ['code' => 'Plan', 'title' => 'Resident', 'cat' => 'Focus', 'party' => 'Written by', 'date1' => 'Started', 'date2' => 'Review', 'status' => 'State'], 'notes_label' => 'Plan'],
        'search' => ['code', 'title', 'cat', 'party'],
        'kpis' => [['Plans due for review', "SELECT COUNT(*) " . $rec('ncare') . " AND status IN ('active','reviewed') AND date2 IS NOT NULL AND date2 <= CURDATE()", 'clock', 'review date reached', 'ncare', []]],
        'reports' => ['careplan_register' => ['label' => 'Care plan register', 'desc' => 'Resident care plans in the period.', 'date' => 'date1']],
    ],
    'mar' => [
        'types' => ['nursing-home'],
        'title' => 'Medication rounds', 'singular' => 'medication entry', 'icon' => 'check', 'group' => 'Care',
        'perm' => 'manage_prescriptions', 'prefix' => 'MAR-', 'queue' => true,
        'sub' => 'Today’s medicines to give. Mark given or withheld, with a reason when a dose is held back.',
        'fields' => [
            'title' => ['label' => 'Resident name', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Medicine and dose', 'required' => true, 'max' => 80],
            'tag' => ['label' => 'Round', 'type' => 'select', 'options' => ['Morning' => 'Morning', 'Noon' => 'Noon', 'Evening' => 'Evening', 'Night' => 'Night', 'Other' => 'Other'], 'default' => 'Morning'],
            'date1' => $date('Date', ['default' => date('Y-m-d'), 'required' => true]),
            'party' => ['label' => 'Given or withheld by', 'max' => 190],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 2, 'max' => 500, 'wide' => true],
        ],
        'columns' => ['code' => 'Entry', 'title' => 'Resident', 'cat' => 'Medicine', 'tag' => 'Round', 'date1' => ['Date', 'date'], 'party' => 'By', 'status' => 'State'],
        'status' => ['due' => ['Due', 'warn'], 'given' => ['Given', 'ok'], 'withheld' => ['Withheld', 'info'], 'missed' => ['Missed', 'bad']],
        'initial' => 'due',
        'flow' => [
            'give' => ['Mark given', 'due', 'given', 'check', 'ask' => ['party' => ['label' => 'Given by', 'required' => true, 'max' => 190]]],
            'hold' => ['Withhold', 'due', 'withheld', 'x', 'ask' => ['party' => ['label' => 'Withheld by', 'required' => true, 'max' => 190], 'notes' => ['label' => 'Reason', 'type' => 'textarea', 'rows' => 2, 'required' => true, 'max' => 500, 'wide' => true]]],
            'miss' => ['Mark missed', 'due', 'missed', 'x'],
        ],
        'print' => ['label' => 'Print entry', 'title' => 'Medication record', 'when' => ['due', 'given', 'withheld', 'missed'], 'rows' => ['code' => 'Entry', 'title' => 'Resident', 'cat' => 'Medicine', 'tag' => 'Round', 'date1' => 'Date', 'party' => 'By', 'status' => 'State'], 'notes_label' => 'Notes'],
        'search' => ['code', 'title', 'cat', 'party', 'tag'],
        'kpis' => [['Doses due today', "SELECT COUNT(*) " . $rec('mar') . " AND date1 = CURDATE() AND status = 'due'", 'clock', 'not given yet', 'mar', ['f_status' => 'due']]],
        'charts' => [['title' => 'Medication entries by state', 'kind' => 'status']],
        'reports' => ['mar_register' => ['label' => 'Medication administration register', 'desc' => 'Medication rounds in the period.', 'date' => 'date1']],
    ],
    'incidents' => [
        'types' => ['nursing-home'],
        'title' => 'Incidents', 'singular' => 'incident', 'icon' => 'bell', 'group' => 'Care',
        'perm' => 'manage_prescriptions', 'prefix' => 'INC-',
        'sub' => 'Falls, medicine errors and other events, from first report to a closed review.',
        'fields' => [
            'title' => ['label' => 'Resident or title', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Type', 'type' => 'select', 'options' => ['Fall' => 'Fall', 'Medication' => 'Medication error', 'Behaviour' => 'Behaviour', 'Injury' => 'Injury', 'Other' => 'Other'], 'default' => 'Fall'],
            'date1' => $date('Date', ['default' => date('Y-m-d'), 'required' => true]),
            'party' => ['label' => 'Reported by', 'max' => 190],
            'notes' => ['label' => 'What happened and what was done', 'type' => 'textarea', 'rows' => 5, 'max' => 3000, 'wide' => true],
        ],
        'columns' => ['code' => 'Incident', 'title' => 'Resident', 'cat' => 'Type', 'date1' => ['Date', 'date'], 'party' => 'Reported by', 'status' => 'State'],
        'status' => ['reported' => ['Reported', 'warn'], 'reviewed' => ['Reviewed', 'info'], 'closed' => ['Closed', 'ok']],
        'initial' => 'reported',
        'flow' => [
            'review' => ['Mark reviewed', 'reported', 'reviewed', 'check', 'ask' => ['notes' => ['label' => 'Review notes', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true]]],
            'close' => ['Close', ['reported', 'reviewed'], 'closed', 'check'],
        ],
        'print' => ['label' => 'Print incident', 'title' => 'Incident report', 'when' => ['reported', 'reviewed', 'closed'], 'rows' => ['code' => 'Number', 'title' => 'Resident', 'cat' => 'Type', 'date1' => 'Date', 'party' => 'Reported by', 'status' => 'State'], 'notes_label' => 'Account'],
        'search' => ['code', 'title', 'cat', 'party'],
        'kpis' => [['Open incidents', "SELECT COUNT(*) " . $rec('incidents') . " AND status IN ('reported','reviewed')", 'bell', 'not closed yet', 'incidents', []]],
        'reports' => ['incident_register' => ['label' => 'Incident register', 'desc' => 'Incidents in the period.', 'date' => 'date1']],
    ],

];
