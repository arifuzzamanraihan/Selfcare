<?php
// Selfcare original config: work lists for ambulance. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;
$vehicleOptions = static function (\Selfcare\Portal $p, ?array $row = null, bool $onlyFree = false): array {
    $rows = \Selfcare\app()->db->all("SELECT code, title, status FROM sc_records WHERE tenant_id = ? AND module = 'vehicles' AND deleted_at IS NULL AND status <> 'retired' ORDER BY code", [$p->tenantId]);
    $o = [];
    foreach ($rows as $r) {
        if ($onlyFree && $r['status'] !== 'available' && ($row === null || (string) $row['cat'] !== (string) $r['code'])) {
            continue;
        }
        $o[(string) $r['code']] = $r['code'] . ($r['title'] ? ' · ' . $r['title'] : '') . ($r['status'] !== 'available' ? ' (' . $r['status'] . ')' : '');
    }
    return $o;
};


return [
    'vehicles' => [
        'types' => ['ambulance'], 'title' => 'Vehicles', 'singular' => 'vehicle', 'icon' => 'truck', 'group' => 'Ambulance',
        'perm' => 'manage_appointments', 'prefix' => '', 'sub' => 'Your fleet, who drives each one and when service and papers are due.',
        'fields' => [
            'code' => ['label' => 'Plate number', 'required' => true, 'max' => 40],
            'title' => ['label' => 'Vehicle', 'max' => 190, 'placeholder' => 'Toyota HiAce ICU'],
            'cat' => ['label' => 'Type', 'type' => 'select', 'options' => ['Basic life support' => 'Basic life support', 'Advanced life support' => 'Advanced life support', 'ICU' => 'ICU ambulance', 'Neonatal' => 'Neonatal', 'Mortuary van' => 'Mortuary van', 'Patient transport' => 'Patient transport'], 'default' => 'Basic life support'],
            'party' => ['label' => 'Driver', 'max' => 190],
            'phone' => ['label' => 'Driver phone', 'type' => 'tel', 'max' => 40],
            'date1' => $date('Next service due'),
            'date2' => $date('Fitness and insurance valid to'),
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['available' => 'Available', 'on_trip' => 'On a trip', 'maintenance' => 'In the workshop', 'retired' => 'Not used any more'], 'required' => true, 'default' => 'available'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Plate', 'title' => 'Vehicle', 'cat' => 'Type', 'party' => 'Driver', 'date1' => ['Service due', 'date'], 'date2' => ['Papers valid to', 'date'], 'status' => 'State'],
        'status' => ['available' => ['Available', 'ok'], 'on_trip' => ['On a trip', 'info'], 'maintenance' => ['In the workshop', 'warn'], 'retired' => ['Not used', 'mute']], 'initial' => 'available', 'status_field' => true,
        'flow' => [
            'workshop' => ['Send to workshop', 'available', 'maintenance', 'settings', 'confirm' => 'Send this vehicle to the workshop? It cannot be dispatched until it is back.'],
            'ready' => ['Back from workshop', 'maintenance', 'available', 'check', 'ask' => ['date1' => ['label' => 'Next service due', 'type' => 'date', 'null' => true], 'notes' => ['label' => 'Work done', 'type' => 'textarea', 'rows' => 3, 'max' => 500, 'wide' => true, 'placeholder' => 'Oil change, tyres, oxygen refilled…']]],
        ],
        'search' => ['code', 'title', 'party', 'cat'], 'sort' => 'code', 'dir' => 'asc', 'code_required' => true,
        'kpis' => [
            ['Vehicles free now', "SELECT COUNT(*) " . $rec('vehicles') . " AND status = 'available'", 'truck', 'ready to go', 'vehicles', ['f_status' => 'available']],
            ['Service or papers due', "SELECT COUNT(*) " . $rec('vehicles') . " AND status <> 'retired' AND (date1 <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) OR date2 <= DATE_ADD(CURDATE(), INTERVAL 14 DAY))", 'clock', 'within 14 days', 'vehicles', []],
        ],
        'charts' => [['title' => 'Vehicles by state', 'kind' => 'status']],
    ],
    'trips' => [
        'types' => ['ambulance'], 'title' => 'Trips', 'singular' => 'trip', 'icon' => 'cal', 'group' => 'Ambulance',
        'perm' => 'manage_appointments', 'prefix' => 'TRP-', 'sub' => 'Calls from request to completed trip, with the vehicle that went.',
        'fields' => [
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Contact phone', 'type' => 'tel', 'max' => 40, 'required' => true],
            'party' => ['label' => 'Caller', 'max' => 190],
            'place_a' => ['label' => 'Pick up from', 'required' => true, 'max' => 190],
            'place_b' => ['label' => 'Take to', 'required' => true, 'max' => 190],
            'date1' => $date('Trip date', ['default' => date('Y-m-d'), 'required' => true]),
            'tag' => ['label' => 'Priority', 'type' => 'select', 'options' => ['Emergency' => 'Emergency (life threatening)', 'Urgent' => 'Urgent', 'Routine' => 'Routine / booked transfer'], 'required' => true, 'default' => 'Urgent'],
            'cat' => ['label' => 'Vehicle', 'type' => 'select', 'options' => 'vehicles_free', 'blank_label' => 'Assign when dispatching', 'help' => 'Only free vehicles are listed.'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true, 'placeholder' => 'Condition, oxygen needed, escort…'],
        ],
        'columns' => ['code' => 'Trip', 'tag' => 'Priority', 'title' => 'Patient', 'place_a' => 'From', 'place_b' => 'To', 'date1' => ['Date', 'date'], 'cat' => 'Vehicle', 'amount' => ['Fare', 'money'], 'status' => 'State'],
        'status' => ['requested' => ['Requested', 'warn'], 'dispatched' => ['Dispatched', 'info'], 'onroute' => ['On the way', 'info'], 'completed' => ['Completed', 'ok'], 'cancelled' => ['Cancelled', 'mute']], 'initial' => 'requested',
        'flow' => [
            'dispatch' => ['Dispatch', 'requested', 'dispatched', 'send', 'ask' => ['cat' => ['label' => 'Vehicle to send', 'type' => 'select', 'options' => 'vehicles_free', 'required' => true], 'notes' => ['label' => 'Crew and instructions', 'type' => 'textarea', 'rows' => 2, 'max' => 300, 'wide' => true, 'placeholder' => 'Driver Karim, paramedic Rina. Oxygen ready.']], 'vehicle' => 'busy'],
            'start' => ['Patient picked up', 'dispatched', 'onroute', 'play'],
            'complete' => ['Complete trip', ['dispatched', 'onroute'], 'completed', 'check', 'ask' => ['qty' => ['label' => 'Distance (km)', 'type' => 'number', 'step' => '0.1', 'min' => 0, 'default' => 0], 'amount' => ['label' => 'Fare charged', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0]], 'vehicle' => 'free'],
            'cancel' => ['Cancel', ['requested', 'dispatched', 'onroute'], 'cancelled', 'x', 'confirm' => 'Cancel this trip?', 'vehicle' => 'free'],
        ],
        'bill' => ['type' => 'trip_invoice', 'when' => ['completed'], 'item' => 'Ambulance trip {code} ({qty} km)', 'price' => 'amount', 'x' => ['vehicle_no' => 'cat', 'pickup' => 'place_a', 'destination' => 'place_b', 'distance_km' => 'qty', 'trip_date' => 'date1']],
        'intake' => true,
        'search' => ['code', 'title', 'phone', 'place_a', 'place_b', 'cat', 'tag'],
        'kpis' => [
            ['Waiting for a vehicle', "SELECT COUNT(*) " . $rec('trips') . " AND status = 'requested'", 'siren', 'not dispatched yet', 'trips', ['f_status' => 'requested']],
            ['Average dispatch time', "SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.created_at, d.created_at))), 0) FROM sc_record_events c JOIN sc_record_events d ON d.record_id = c.record_id AND d.action = 'dispatch' WHERE c.tenant_id = {t} AND c.module = 'trips' AND c.action = 'created' AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'clock', 'minutes, last 30 days', 'trips', [], 'num'],
            ['Trips today', "SELECT COUNT(*) " . $rec('trips') . " AND date1 = CURDATE() AND status <> 'cancelled'", 'cal', 'booked for today', 'trips', []],
            ['On the road', "SELECT COUNT(*) " . $rec('trips') . " AND status IN ('dispatched','onroute')", 'truck', 'trips in progress', 'trips', ['f_status' => 'onroute']],
            ['Earned this month', "SELECT COALESCE(SUM(amount),0) " . $rec('trips') . " AND status = 'completed' AND date1 >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", 'card', 'completed trips', 'trips', ['f_status' => 'completed'], 'money'],
        ],
        'charts' => [['title' => 'Trips per month', 'kind' => 'months', 'sql' => "SELECT DATE_FORMAT(date1, '%Y-%m') m, COUNT(*) n " . $rec('trips') . " AND status <> 'cancelled' AND date1 >= {since} GROUP BY m"], ['title' => 'Trips by state', 'kind' => 'status']],
        'dynamic' => ['vehicles_free' => $vehicleOptions],
    ],
];
