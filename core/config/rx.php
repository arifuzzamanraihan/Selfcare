<?php
// Selfcare original code. Prescription settings: which kinds of vendor write prescriptions and what is special for each.
// Fields follow the e-prescription guidance (ISO 17523, EU eHealth guidelines, ISMP safe prescribing): patient identity, prescriber identity and
// registration, medicine with strength and form, dose, route, frequency, duration or quantity, indication, allergies shown while prescribing.
declare(strict_types=1);

$forms = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Infusion', 'Drops', 'Eye drops', 'Ear drops', 'Nasal spray', 'Inhaler', 'Cream', 'Ointment', 'Gel', 'Lotion', 'Suppository', 'Sachet', 'Powder', 'Patch', 'Other'];
return [
    'types' => [
        'hospital' => ['title' => 'PRESCRIPTION', 'forms' => $forms, 'extra' => [['ward', 'Ward / bed']]],
        'clinic' => ['title' => 'PRESCRIPTION', 'forms' => $forms, 'extra' => []],
        'specialty-clinic' => ['title' => 'PRESCRIPTION', 'forms' => $forms, 'extra' => []],
        'dental-clinic' => ['title' => 'PRESCRIPTION', 'forms' => ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Gel', 'Mouthwash', 'Ointment', 'Cream', 'Drops', 'Other'], 'extra' => [['tooth', 'Tooth / area treated']], 'note' => 'Complete the full antibiotic course. Return if swelling, fever or severe pain continues.'],
        'eye-clinic' => ['title' => 'PRESCRIPTION', 'forms' => ['Eye drops', 'Eye ointment', 'Tablet', 'Capsule', 'Syrup', 'Injection', 'Gel', 'Other'], 'extra' => [['eye', 'Eye', 'select', ['Right', 'Left', 'Both']], ['va', 'Vision (R / L)']], 'route' => 'Eye'],
        'maternity-center' => ['title' => 'PRESCRIPTION', 'forms' => $forms, 'extra' => [['gest', 'Gestation (weeks) / LMP'], ['edd', 'Expected delivery date', 'date']], 'note' => 'Do not take any other medicine in pregnancy or breastfeeding without asking your doctor.'],
        'mental-health' => ['title' => 'PRESCRIPTION', 'forms' => ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Drops', 'Patch', 'Other'], 'extra' => [['review', 'Next medication review', 'date']], 'note' => 'Do not stop this medicine suddenly. Keep it away from children. Avoid alcohol.'],
        'alternative-medicine' => ['title' => 'PRESCRIPTION', 'forms' => ['Tablet', 'Capsule', 'Powder', 'Decoction', 'Syrup', 'Oil', 'Paste', 'Ointment', 'Drops', 'Other'], 'extra' => [['diet', 'Diet and lifestyle']]],
        'dialysis-center' => ['title' => 'PRESCRIPTION', 'forms' => $forms, 'extra' => [['dry', 'Dry weight (kg)'], ['access', 'Dialysis access']], 'note' => 'Follow the fluid and diet limits given by the dialysis team.'],
        'telemedicine' => ['title' => 'E-PRESCRIPTION (ONLINE CONSULTATION)', 'forms' => $forms, 'extra' => [['mode', 'Consultation by', 'select', ['Video call', 'Voice call', 'Chat']]], 'note' => 'Issued after an online consultation. For a medical emergency go to the nearest hospital.'],
    ],
    'routes' => ['PO (by mouth)', 'IV', 'IM', 'SC', 'Topical', 'Eye', 'Ear', 'Nasal', 'Inhaled', 'PR', 'SL', 'Local (mouth)', 'Other'],
    'freq' => ['1+0+0 (morning)', '0+1+0 (noon)', '0+0+1 (night)', '1+0+1 (twice daily)', '1+1+1 (three times daily)', '1+1+1+1 (four times daily)', 'Every 6 hours', 'Every 8 hours', 'Every 12 hours', 'Once a week', 'Single dose now', 'When needed (PRN)'],
    'timing' => ['', 'Before food', 'After food', 'With food', 'Empty stomach', 'At bedtime'],
    'units' => ['days', 'weeks', 'months', 'ongoing'],
    'sex' => ['Male', 'Female', 'Other'],
    // allergy classes: the allergy word on the left warns for every drug word on the right
    'classes' => [
        'penicillin' => ['penicillin', 'amoxicillin', 'ampicillin', 'cloxacillin', 'flucloxacillin', 'piperacillin', 'amoxiclav', 'augmentin', 'co-amoxiclav'],
        'sulfa' => ['sulfa', 'sulfamethoxazole', 'co-trimoxazole', 'cotrimoxazole', 'sulfasalazine', 'trimethoprim'],
        'nsaid' => ['ibuprofen', 'diclofenac', 'naproxen', 'ketorolac', 'aceclofenac', 'piroxicam', 'aspirin', 'celecoxib', 'etoricoxib', 'mefenamic', 'indomethacin'],
        'aspirin' => ['aspirin', 'ibuprofen', 'diclofenac', 'naproxen', 'ketorolac', 'aceclofenac'],
        'cephalosporin' => ['cef', 'cephalexin', 'cephradine', 'cefixime', 'ceftriaxone', 'cefuroxime', 'cefpodoxime'],
        'macrolide' => ['azithromycin', 'clarithromycin', 'erythromycin'],
        'quinolone' => ['ciprofloxacin', 'levofloxacin', 'ofloxacin', 'moxifloxacin', 'norfloxacin'],
        'codeine' => ['codeine', 'tramadol', 'morphine'],
        'latex' => [],
    ],
];
