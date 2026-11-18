<?php
// Selfcare original config: work lists for blood bank. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;
$groups = ['A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-'];
$components = ['Whole blood' => 'Whole blood', 'Packed red cells' => 'Packed red cells', 'Platelets' => 'Platelets', 'Plasma' => 'Plasma', 'Cryoprecipitate' => 'Cryoprecipitate'];

return [
    'donors' => [
        'types' => ['blood-bank'], 'title' => 'Donor eligibility', 'singular' => 'donor', 'icon' => 'heart', 'group' => 'Blood bank',
        'perm' => 'manage_blood_bank', 'prefix' => 'DN-', 'sub' => 'When each donor gave last and when they can give again (90 days apart). Record a donation and the bag is added next.',
        'fields' => [
            'title' => ['label' => 'Donor name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40, 'required' => true],
            'cat' => ['label' => 'Blood group', 'type' => 'select', 'options' => $groups, 'required' => true],
            'place_a' => ['label' => 'Area or address', 'max' => 190],
            'date1' => $date('Last donated on'),
            'date2' => $date('Can give again from', ['help' => 'Left empty it is worked out as 90 days after the last donation.']),
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['eligible' => 'Can donate', 'resting' => 'Resting after a donation', 'deferred' => 'Not allowed to donate', 'inactive' => 'Not active'], 'required' => true, 'default' => 'eligible'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true, 'help' => 'For example the reason a donor is not allowed to give.'],
        ],
        'columns' => ['code' => 'ID', 'title' => 'Donor', 'cat' => 'Group', 'phone' => 'Phone', 'qty' => ['Donations', 'num'], 'date1' => ['Last gave', 'date'], 'date2' => ['Can give from', 'date'], 'status' => 'State'],
        'status' => ['eligible' => ['Can donate', 'ok'], 'resting' => ['Resting', 'info'], 'deferred' => ['Not allowed', 'bad'], 'inactive' => ['Not active', 'mute']], 'initial' => 'eligible', 'status_field' => true,
        'flow' => [
            'donated' => ['Record donation', ['eligible'], 'resting', 'drop', 'confirm' => 'Record a donation today? The donor will rest for 90 days.', 'then' => ['units', ['title' => 'title', 'cat' => 'cat']]],
        ],
        'search' => ['code', 'title', 'phone', 'cat'],
        'kpis' => [
            ['Donors who can give', "SELECT COUNT(*) " . $rec('donors') . " AND status = 'eligible'", 'heart', 'ready to be called', 'donors', ['f_status' => 'eligible']],
        ],
        'charts' => [['title' => 'Donors by blood group', 'kind' => 'group', 'col' => 'cat', 'order' => array_keys($groups)]],
    ],
    'units' => [
        'types' => ['blood-bank'], 'title' => 'Blood units', 'singular' => 'blood unit', 'icon' => 'drop', 'group' => 'Blood bank',
        'perm' => 'manage_blood_bank', 'prefix' => 'BAG-', 'sub' => 'Every bag in the fridge. A bag is held back until it is screened, and it expires on its date.',
        'fields' => [
            'title' => ['label' => 'Donor name', 'max' => 190],
            'cat' => ['label' => 'Blood group', 'type' => 'select', 'options' => $groups, 'required' => true],
            'party' => ['label' => 'Component', 'type' => 'select', 'options' => $components, 'required' => true, 'default' => 'Whole blood'],
            'qty' => ['label' => 'Volume (ml)', 'type' => 'number', 'step' => '1', 'min' => 1, 'default' => 450],
            'date1' => $date('Collected on', ['required' => true, 'default' => date('Y-m-d')]),
            'date2' => $date('Expires on', ['help' => 'Left empty it is set from the component: 35 days for whole blood and red cells, 5 for platelets, a year for plasma.']),
            'place_b' => ['label' => 'Fridge or shelf', 'max' => 190],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Bag', 'cat' => 'Group', 'party' => 'Component', 'qty' => ['ml', 'num'], 'date1' => ['Collected', 'date'], 'date2' => ['Expires', 'date'], 'place_a' => 'Issued to', 'status' => 'State'],
        'status' => ['quarantine' => ['Being screened', 'warn'], 'available' => ['Available', 'ok'], 'reserved' => ['Reserved', 'info'], 'issued' => ['Issued', 'mute'], 'expired' => ['Expired', 'bad'], 'discarded' => ['Discarded', 'bad']], 'initial' => 'quarantine',
        'flow' => [
            'release' => ['Release (screened)', 'quarantine', 'available', 'check', 'confirm' => 'The screening tests are clear? The bag will become available.'],
            'reserve' => ['Reserve', 'available', 'reserved', 'lock', 'ask' => ['place_a' => ['label' => 'Reserved for', 'required' => true, 'max' => 190, 'placeholder' => 'Patient or hospital']]],
            'unreserve' => ['Free again', 'reserved', 'available', 'unlock', 'clear' => ['place_a']],
            'issue' => ['Issue', ['available', 'reserved'], 'issued', 'send', 'ask' => ['place_a' => ['label' => 'Issued to', 'required' => true, 'max' => 190, 'placeholder' => 'Patient and hospital']]],
            'discard' => ['Discard', ['quarantine', 'available', 'reserved'], 'discarded', 'trash', 'ask' => ['notes' => ['label' => 'Why is it discarded?', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true, 'placeholder' => 'Positive screening, torn bag…']]],
        ],
        'search' => ['code', 'title', 'cat', 'party', 'place_a'],
        'expire' => ['status' => ['quarantine', 'available', 'reserved'], 'col' => 'date2', 'to' => 'expired'],
        'kpis' => [
            ['Units available', "SELECT COALESCE(SUM(1),0) " . $rec('units') . " AND status = 'available'", 'drop', 'ready to issue', 'units', ['f_status' => 'available']],
            ['Expire within 7 days', "SELECT COUNT(*) " . $rec('units') . " AND status IN ('available','reserved') AND date2 BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)", 'clock', 'use these first', 'units', ['f_status' => 'available']],
            ['Being screened', "SELECT COUNT(*) " . $rec('units') . " AND status = 'quarantine'", 'flask', 'not released yet', 'units', ['f_status' => 'quarantine']],
        ],
        'charts' => [['title' => 'Available units by blood group', 'kind' => 'group', 'col' => 'cat', 'where' => "status = 'available'", 'order' => array_keys($groups)], ['title' => 'Units by state', 'kind' => 'status']],
    ],
    'bloodreq' => [
        'types' => ['blood-bank'], 'title' => 'Blood orders', 'singular' => 'blood order', 'icon' => 'cal', 'group' => 'Blood bank',
        'perm' => 'manage_blood_bank', 'prefix' => 'BO-', 'sub' => 'Hospitals and families who order blood from you. Website requests are in the Blood requests list.',
        'fields' => [
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'party' => ['label' => 'Hospital or ward', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Blood group needed', 'type' => 'select', 'options' => $groups, 'required' => true],
            'place_a' => ['label' => 'Component', 'type' => 'select', 'options' => $components, 'default' => 'Whole blood'],
            'qty' => ['label' => 'Units needed', 'type' => 'number', 'step' => '1', 'min' => 1, 'default' => 1],
            'date1' => $date('Needed by', ['default' => date('Y-m-d')]),
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Order', 'title' => 'Patient', 'party' => 'Hospital', 'cat' => 'Group', 'qty' => ['Units', 'num'], 'date1' => ['Needed by', 'date'], 'status' => 'State'],
        'status' => ['pending' => ['Waiting', 'warn'], 'fulfilled' => ['Given', 'ok'], 'rejected' => ['Could not give', 'bad']], 'initial' => 'pending',
        'flow' => [
            'fulfil' => ['Mark as given', 'pending', 'fulfilled', 'check', 'confirm' => 'You have issued the blood for this order?'],
            'reject' => ['Could not give', 'pending', 'rejected', 'x', 'ask' => ['notes' => ['label' => 'Why not?', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 500, 'wide' => true, 'placeholder' => 'No stock of this group…']]],
        ],
        'search' => ['code', 'title', 'party', 'cat', 'phone'],
        'kpis' => [['Blood orders waiting', "SELECT COUNT(*) " . $rec('bloodreq') . " AND status = 'pending'", 'cal', 'for blood', 'bloodreq', ['f_status' => 'pending']]],
    ],
];
