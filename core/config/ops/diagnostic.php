<?php
// Selfcare original config: work lists for laboratories and diagnostic centres (test catalogue). Loaded by core/Ops.php with the other files in this folder.
// Format is described at the top of core/Ops.php.
declare(strict_types=1);

$rec = static fn (string $m): string => "FROM sc_records WHERE tenant_id = {t} AND module = '$m' AND deleted_at IS NULL";
$date = static fn (string $label, array $extra = []): array => ['label' => $label, 'type' => 'date', 'null' => true] + $extra;

return [

    'catalogue' => [
        'types' => ['laboratory', 'diagnostic-center'],
        'title' => 'Test catalogue', 'singular' => 'test', 'icon' => 'flask', 'group' => 'Laboratory',
        'perm' => 'manage_prescriptions', 'prefix' => '',
        'sub' => 'The tests you offer, with sample type, units, reference range and fee. Use these names when you register a sample.',
        'fields' => [
            'title' => ['label' => 'Test or panel name', 'required' => true, 'max' => 190],
            'cat' => ['label' => 'Department', 'type' => 'select', 'options' => ['Haematology' => 'Haematology', 'Biochemistry' => 'Biochemistry', 'Microbiology' => 'Microbiology', 'Immunology' => 'Immunology', 'Cytology' => 'Cytology', 'Other' => 'Other'], 'default' => 'Biochemistry'],
            'place_a' => ['label' => 'Sample type', 'type' => 'select', 'options' => ['Blood' => 'Blood', 'Urine' => 'Urine', 'Stool' => 'Stool', 'Swab' => 'Swab', 'Sputum' => 'Sputum', 'Tissue' => 'Tissue', 'Other' => 'Other'], 'default' => 'Blood'],
            'place_b' => ['label' => 'Units', 'max' => 40, 'placeholder' => 'mg/dL, g/L, %'],
            'amount' => ['label' => 'Fee', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            'status' => ['label' => 'State', 'type' => 'select', 'options' => ['active' => 'Offered', 'inactive' => 'Not offered now'], 'required' => true, 'default' => 'active'],
            'notes' => ['label' => 'Reference range and remarks', 'type' => 'textarea', 'rows' => 4, 'max' => 2000, 'wide' => true, 'placeholder' => 'e.g. TSH 0.4–4.0 mIU/L (adult)'],
        ],
        'columns' => ['title' => 'Test', 'cat' => 'Department', 'place_a' => 'Sample', 'place_b' => 'Units', 'amount' => ['Fee', 'money'], 'status' => 'State'],
        'status' => ['active' => ['Offered', 'ok'], 'inactive' => ['Not offered', 'mute']], 'initial' => 'active', 'status_field' => true,
        'search' => ['title', 'cat', 'place_a'], 'sort' => 'title', 'dir' => 'asc',
        'kpis' => [['Tests on offer', "SELECT COUNT(*) " . $rec('catalogue') . " AND status = 'active'", 'flask', 'in the catalogue', 'catalogue', ['f_status' => 'active']]],
    ],

];
