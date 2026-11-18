<?php
// Selfcare original config: work lists for pharmacy. Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [
    'rx' => [
        'types' => ['pharmacy'], 'title' => 'Prescription orders', 'singular' => 'prescription order', 'icon' => 'file', 'group' => 'Pharmacy',
        'perm' => 'manage_appointments', 'prefix' => 'RX-', 'sub' => 'Prescriptions that arrive by phone, website or at the counter. Verify, then dispense.',
        'fields' => [
            'title' => ['label' => 'Patient name', 'required' => true, 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'party' => ['label' => 'Prescribed by', 'max' => 190, 'placeholder' => 'Doctor or clinic'],
            'cat' => ['label' => 'Came in by', 'type' => 'select', 'options' => ['Counter' => 'At the counter', 'Phone' => 'By phone', 'Website' => 'Through the website', 'Delivery' => 'Home delivery'], 'default' => 'Counter'],
            'date1' => $date('Prescription date', ['default' => date('Y-m-d')]),
            'amount' => ['label' => 'Estimated total', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'notes' => ['label' => 'Medicines and notes', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true],
        ],
        'columns' => ['code' => 'Order', 'title' => 'Patient', 'party' => 'Prescribed by', 'cat' => 'Came in by', 'date1' => ['Date', 'date'], 'amount' => ['Total', 'money'], 'status' => 'State'],
        'status' => ['received' => ['Received', 'warn'], 'verified' => ['Verified', 'info'], 'dispensed' => ['Dispensed', 'ok'], 'cancelled' => ['Cancelled', 'mute']], 'initial' => 'received',
        'flow' => [
            'verify' => ['Verify', 'received', 'verified', 'check'],
            'dispense' => ['Dispense', 'verified', 'dispensed', 'check'],
            'cancel' => ['Cancel', ['received', 'verified'], 'cancelled', 'x', 'confirm' => 'Cancel this order?'],
        ],
        'intake' => true,
        'bill' => ['type' => 'sales_invoice', 'when' => ['verified', 'dispensed'], 'x' => ['prescriber' => 'party', 'rx_no' => 'code']],
        'search' => ['code', 'title', 'party', 'phone'],
        'kpis' => [
            ['Waiting to verify', "SELECT COUNT(*) " . $rec('rx') . " AND status = 'received'", 'file', 'prescriptions to check', 'rx', ['f_status' => 'received']],
            ['Ready to dispense', "SELECT COUNT(*) " . $rec('rx') . " AND status = 'verified'", 'check', 'verified, not handed over', 'rx', ['f_status' => 'verified']],
        ],
        'charts' => [['title' => 'Prescription orders by state', 'kind' => 'status']],
    ],
    'suppliers' => [
        'types' => ['pharmacy'], 'title' => 'Suppliers', 'singular' => 'supplier', 'icon' => 'truck', 'group' => 'Pharmacy',
        'perm' => 'manage_medicines', 'prefix' => '', 'sub' => 'Wholesalers and companies you buy from.',
        'fields' => [
            'title' => ['label' => 'Supplier name', 'required' => true, 'max' => 190],
            'party' => ['label' => 'Contact person', 'max' => 190],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'max' => 40],
            'cat' => ['label' => 'Supplies', 'type' => 'select', 'options' => ['Medicines' => 'Medicines', 'Surgical items' => 'Surgical items', 'Supplements' => 'Supplements', 'Baby and personal care' => 'Baby and personal care', 'Other' => 'Other'], 'default' => 'Medicines'],
            'place_a' => ['label' => 'Address', 'max' => 190, 'wide' => true],
            'amount' => ['label' => 'Amount we owe them', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Not used now'], 'required' => true, 'default' => 'active'],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 3, 'max' => 1000, 'wide' => true],
        ],
        'columns' => ['title' => 'Supplier', 'party' => 'Contact', 'phone' => 'Phone', 'cat' => 'Supplies', 'amount' => ['We owe', 'money'], 'status' => 'State'],
        'status' => ['active' => ['Active', 'ok'], 'inactive' => ['Not used now', 'mute']], 'initial' => 'active', 'status_field' => true,
        'search' => ['title', 'party', 'phone'], 'sort' => 'title', 'dir' => 'asc',
    ],
];
