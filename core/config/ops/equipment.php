<?php
// Selfcare original config: work lists for medical equipment suppliers (rental catalogue, rentals and service). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

$itemOptions = static function (\Selfcare\Portal $p, ?array $row = null, bool $onlyFree = false): array {
    $rows = \Selfcare\app()->db->all("SELECT code, title, status FROM sc_records WHERE tenant_id = ? AND module = 'eqitems' AND deleted_at IS NULL AND status = 'active' ORDER BY title", [$p->tenantId]);
    $o = [];
    foreach ($rows as $r) {
        $o[(string) $r['code']] = $r['code'] . ' · ' . $r['title'];
    }
    return $o;
};

return [

    'eqitems' => [
        'types' => ['equipment-supplier'],
        'title' => 'Rental catalogue', 'singular' => 'catalogue item', 'icon' => 'box', 'group' => 'Equipment',
        'perm' => 'manage_catalog', 'prefix' => 'EQ-',
        'sub' => 'Items you rent out, with a daily or weekly rate. Sales stock stays on the Stock page.',
        'fields' => [
            'code' => ['label' => 'Item code', 'max' => 40],
            'title' => ['label' => 'Item name', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Category', 'type' => 'select', 'options' => ['Mobility' => 'Mobility (wheelchair, walker)', 'Respiratory' => 'Respiratory (oxygen, CPAP)', 'Bed and support' => 'Bed and support', 'Monitoring' => 'Monitoring', 'Other' => 'Other'], 'default' => 'Mobility'],
            'amount' => ['label' => 'Rental rate', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0, 'help' => 'Per the period you usually charge (day or week).'],
            'qty' => ['label' => 'Units on hand for rent', 'type' => 'number', 'step' => '1', 'min' => 0, 'default' => 1],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'Available to rent', 'inactive' => 'Not offered'], 'required' => true, 'default' => 'active'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['code' => 'Code', 'title' => 'Item', 'cat' => 'Category', 'qty' => ['On hand', 'num'], 'amount' => ['Rate', 'money'], 'status' => 'State'],
        'status' => ['active' => ['Available', 'ok'], 'inactive' => ['Not offered', 'mute']], 'initial' => 'active', 'status_field' => true,
        'search' => ['code', 'title', 'cat'], 'sort' => 'title', 'dir' => 'asc',
    ],
    'rentals' => [
        'types' => ['equipment-supplier'],
        'title' => 'Rentals', 'singular' => 'rental', 'icon' => 'truck', 'group' => 'Equipment',
        'perm' => 'manage_appointments', 'prefix' => 'RNT-', 'queue' => true,
        'sub' => 'Rental jobs from reservation to return. Overdue jobs are marked automatically after the due date.',
        'fields' => [
            'title' => ['label' => 'Customer name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40, 'required' => true],
            'cat' => ['label' => 'Item', 'type' => 'select', 'options' => 'eq_items', 'blank_label' => 'Choose…'],
            'place_a' => ['label' => 'Serial or asset no', 'max' => 80],
            'place_b' => ['label' => 'Delivery address', 'max' => 190, 'wide' => true],
            'date1' => $date('Out from', ['default' => date('Y-m-d'), 'required' => true]),
            'date2' => $date('Due back', ['required' => true]),
            'amount' => ['label' => 'Rental charge', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Notes (deposit, condition)', 'type' => 'textarea', 'rows' => 3, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Rental', 'title' => 'Customer', 'cat' => 'Item', 'date1' => ['Out from', 'date'], 'date2' => ['Due back', 'date'], 'amount' => ['Charge', 'money'], 'status' => 'State'],
        'status' => ['reserved' => ['Reserved', 'info'], 'out' => ['Out', 'warn'], 'overdue' => ['Overdue', 'bad'], 'returned' => ['Returned', 'ok'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'reserved',
        'expire' => ['status' => ['out'], 'col' => 'date2', 'to' => 'overdue'],
        'flow' => [
            'dispatch' => ['Mark out', 'reserved', 'out', 'send'],
            'return' => ['Record return', ['out', 'overdue'], 'returned', 'check', 'ask' => ['notes' => ['label' => 'Condition on return', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true]]],
            'cancel' => ['Cancel', 'reserved', 'cancelled', 'x', 'confirm' => 'Cancel this rental?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'rental_invoice', 'when' => ['reserved', 'out', 'overdue', 'returned'], 'item' => 'Rental {code} ({cat})', 'price' => 'amount', 'x' => ['rental_from' => 'date1', 'rental_to' => 'date2', 'serial_nos' => 'place_a']],
        'print' => ['label' => 'Print slip', 'title' => 'Rental slip', 'when' => ['reserved', 'out', 'overdue', 'returned'], 'rows' => ['code' => 'Rental', 'title' => 'Customer', 'phone' => 'Phone', 'cat' => 'Item', 'place_a' => 'Serial', 'place_b' => 'Address', 'date1' => 'Out from', 'date2' => 'Due back', 'amount' => 'Charge', 'status' => 'State'], 'notes_label' => 'Notes'],
        'search' => ['code', 'title', 'phone', 'cat', 'place_a'],
        'dynamic' => ['eq_items' => $itemOptions],
        'kpis' => [
            ['Out now', "SELECT COUNT(*) " . $rec('rentals') . " AND status = 'out'", 'truck', 'with customers', 'rentals', ['f_status' => 'out']],
            ['Overdue', "SELECT COUNT(*) " . $rec('rentals') . " AND status = 'overdue'", 'clock', 'past the due date', 'rentals', ['f_status' => 'overdue']],
        ],
        'charts' => [['title' => 'Rentals by state', 'kind' => 'status']],
        'reports' => ['rental_register' => ['label' => 'Rental register', 'desc' => 'Equipment rentals in the period.', 'date' => 'date1']],
    ],
    'service' => [
        'types' => ['equipment-supplier'],
        'title' => 'Service jobs', 'singular' => 'service job', 'icon' => 'settings', 'group' => 'Equipment',
        'perm' => 'manage_medicines', 'prefix' => 'SRV-',
        'sub' => 'Maintenance, installation and repair jobs.',
        'fields' => [
            'title' => ['label' => 'Customer name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Item or model', 'max' => 80],
            'place_a' => ['label' => 'Job type', 'type' => 'select', 'options' => ['Installation' => 'Installation', 'Maintenance' => 'Maintenance / AMC', 'Repair' => 'Repair', 'Other' => 'Other'], 'default' => 'Repair'],
            'date1' => $date('Job date', ['default' => date('Y-m-d')]),
            'amount' => ['label' => 'Charge', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Work done', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Job', 'title' => 'Customer', 'place_a' => 'Type', 'cat' => 'Item', 'date1' => ['Date', 'date'], 'amount' => ['Charge', 'money'], 'status' => 'State'],
        'status' => ['open' => ['Open', 'warn'], 'in_progress' => ['In progress', 'info'], 'done' => ['Done', 'ok'], 'cancelled' => ['Cancelled', 'mute']],
        'initial' => 'open',
        'flow' => [
            'start' => ['Start work', 'open', 'in_progress', 'play'],
            'complete' => ['Complete', ['open', 'in_progress'], 'done', 'check', 'ask' => ['notes' => ['label' => 'Work done', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true], 'amount' => ['label' => 'Charge', 'type' => 'number', 'step' => '0.01', 'min' => 0]]],
            'cancel' => ['Cancel', ['open', 'in_progress'], 'cancelled', 'x', 'confirm' => 'Cancel this job?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'equipment_invoice', 'when' => ['done'], 'item' => '{place_a} — {cat}', 'price' => 'amount'],
        'print' => ['label' => 'Print job sheet', 'title' => 'Service job sheet', 'when' => ['in_progress', 'done'], 'rows' => ['code' => 'Job', 'title' => 'Customer', 'phone' => 'Phone', 'place_a' => 'Type', 'cat' => 'Item', 'date1' => 'Date', 'amount' => 'Charge', 'status' => 'State'], 'notes_label' => 'Work done'],
        'search' => ['code', 'title', 'phone', 'cat', 'place_a'],
        'kpis' => [['Open service jobs', "SELECT COUNT(*) " . $rec('service') . " AND status IN ('open','in_progress')", 'settings', 'not finished', 'service', []]],
        'charts' => [['title' => 'Service jobs by state', 'kind' => 'status']],
        'reports' => ['service_register' => ['label' => 'Service job register', 'desc' => 'Installation and repair jobs in the period.', 'date' => 'date1']],
    ],

];
