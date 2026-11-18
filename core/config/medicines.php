<?php
// Selfcare original code. Starter medicine list for the doctors' medicine master (Pakistan and Bangladesh practice, generic first, common brands).
// Every vendor gets a private copy the first time it opens the list; the vendor can edit, add and remove. Brands vary by country, so they are only hints.
// Line format: generic | brand | strength | form | class | dose | how often | when | days (0 = ongoing) | route
// How often: M morning, N night, BD twice daily, TDS three times, QDS four times, Q6/Q8/Q12 every n hours, WK weekly, STAT single dose, PRN when needed.
// When: B before food, A after food, W with food, E empty stomach, S bedtime, - any time.
declare(strict_types=1);

$freq = ['M' => '1+0+0 (morning)', 'N' => '0+0+1 (night)', 'BD' => '1+0+1 (twice daily)', 'TDS' => '1+1+1 (three times daily)', 'QDS' => '1+1+1+1 (four times daily)', 'Q6' => 'Every 6 hours', 'Q8' => 'Every 8 hours', 'Q12' => 'Every 12 hours', 'WK' => 'Once a week', 'STAT' => 'Single dose now', 'PRN' => 'When needed (PRN)'];
$when = ['B' => 'Before food', 'A' => 'After food', 'W' => 'With food', 'E' => 'Empty stomach', 'S' => 'At bedtime', '-' => ''];
$route = ['PO' => 'PO (by mouth)', 'IV' => 'IV', 'IM' => 'IM', 'SC' => 'SC', 'TOP' => 'Topical', 'EYE' => 'Eye', 'EAR' => 'Ear', 'NAS' => 'Nasal', 'INH' => 'Inhaled', 'PR' => 'PR', 'SL' => 'SL', 'LOC' => 'Local (mouth)'];

$raw = <<<'TXT'
Paracetamol|Napa / Panadol|500 mg|Tablet|Pain and fever|1 tablet|TDS|A|3|PO
Paracetamol|Calpol / Napa|120 mg/5 ml|Syrup|Pain and fever|5 ml|TDS|A|3|PO
Paracetamol|Napa Extend|665 mg|Tablet|Pain and fever|1 tablet|Q8|A|3|PO
Paracetamol|Fevago|1 g/100 ml|Infusion|Pain and fever|100 ml|STAT|-|1|IV
Paracetamol + caffeine|Panadol Extra|500 mg + 65 mg|Tablet|Pain and fever|1 tablet|TDS|A|3|PO
Ibuprofen|Brufen / Ibuprofen|400 mg|Tablet|Pain relief (NSAID)|1 tablet|TDS|A|3|PO
Ibuprofen|Brufen|100 mg/5 ml|Suspension|Pain relief (NSAID)|5 ml|TDS|A|3|PO
Diclofenac sodium|Voltral / Olfen|50 mg|Tablet|Pain relief (NSAID)|1 tablet|BD|A|5|PO
Diclofenac sodium|Voltaren / Cataflam|75 mg/3 ml|Injection|Pain relief (NSAID)|1 ampoule|STAT|-|1|IM
Diclofenac gel|Voltral Emulgel|1%|Gel|Pain relief (NSAID)|Apply a thin layer|TDS|-|7|TOP
Naproxen|Synflex / Naprosyn|550 mg|Tablet|Pain relief (NSAID)|1 tablet|BD|A|5|PO
Aceclofenac|Aceclo / Zerodol|100 mg|Tablet|Pain relief (NSAID)|1 tablet|BD|A|5|PO
Etoricoxib|Arcoxia / Etorix|90 mg|Tablet|Pain relief (NSAID)|1 tablet|M|A|5|PO
Mefenamic acid|Ponstan / Ponstan Forte|500 mg|Tablet|Pain relief (NSAID)|1 tablet|TDS|A|3|PO
Tramadol|Tramal|50 mg|Capsule|Strong pain relief|1 capsule|BD|A|3|PO
Pregabalin|Lyrica / Pregabalin|75 mg|Capsule|Nerve pain|1 capsule|BD|-|14|PO
Gabapentin|Neurontin|300 mg|Capsule|Nerve pain|1 capsule|N|-|14|PO
Amoxicillin|Amoxil / Moxacil|500 mg|Capsule|Antibiotic|1 capsule|TDS|-|5|PO
Amoxicillin|Amoxil|250 mg/5 ml|Suspension|Antibiotic|5 ml|TDS|-|5|PO
Amoxicillin + clavulanic acid|Augmentin / Moxclav|625 mg|Tablet|Antibiotic|1 tablet|BD|W|5|PO
Amoxicillin + clavulanic acid|Augmentin|228.5 mg/5 ml|Suspension|Antibiotic|5 ml|BD|W|5|PO
Azithromycin|Zithromax / Zimax|500 mg|Tablet|Antibiotic|1 tablet|M|E|3|PO
Azithromycin|Zithromax|200 mg/5 ml|Suspension|Antibiotic|5 ml|M|E|3|PO
Clarithromycin|Klaricid / Claribid|500 mg|Tablet|Antibiotic|1 tablet|BD|A|7|PO
Cefixime|Cefspan / Cef-3|400 mg|Capsule|Antibiotic|1 capsule|M|-|5|PO
Cefixime|Cefspan|100 mg/5 ml|Suspension|Antibiotic|5 ml|BD|-|5|PO
Cefuroxime|Zinnat / Cefotil|500 mg|Tablet|Antibiotic|1 tablet|BD|A|5|PO
Cefpodoxime|Orelox / Cefodox|200 mg|Tablet|Antibiotic|1 tablet|BD|A|5|PO
Cephalexin|Keflex|500 mg|Capsule|Antibiotic|1 capsule|QDS|-|5|PO
Ceftriaxone|Rocephin / Ceftrix|1 g|Injection|Antibiotic|1 vial|Q12|-|3|IV
Cefotaxime|Claforan|1 g|Injection|Antibiotic|1 vial|Q12|-|3|IV
Ciprofloxacin|Ciproxin / Cipro|500 mg|Tablet|Antibiotic|1 tablet|BD|-|5|PO
Levofloxacin|Tavanic / Leflox|500 mg|Tablet|Antibiotic|1 tablet|M|-|5|PO
Moxifloxacin|Avelox / Moxiflox|400 mg|Tablet|Antibiotic|1 tablet|M|-|5|PO
Metronidazole|Flagyl|400 mg|Tablet|Antibiotic (anaerobes, amoeba)|1 tablet|TDS|A|5|PO
Metronidazole|Flagyl|200 mg/5 ml|Suspension|Antibiotic (anaerobes, amoeba)|5 ml|TDS|A|5|PO
Doxycycline|Vibramycin / Doxy|100 mg|Capsule|Antibiotic|1 capsule|BD|A|7|PO
Cotrimoxazole|Septran|960 mg|Tablet|Antibiotic|1 tablet|BD|A|5|PO
Nitrofurantoin|Macrobid|100 mg|Capsule|Antibiotic (urine)|1 capsule|BD|W|5|PO
Flucloxacillin|Flucil|500 mg|Capsule|Antibiotic|1 capsule|QDS|E|7|PO
Clindamycin|Dalacin C|300 mg|Capsule|Antibiotic|1 capsule|Q8|-|7|PO
Gentamicin|Garamycin|80 mg|Injection|Antibiotic|1 ampoule|Q12|-|3|IM
Fluconazole|Diflucan|150 mg|Capsule|Antifungal|1 capsule|STAT|-|1|PO
Terbinafine|Lamisil|250 mg|Tablet|Antifungal|1 tablet|M|-|14|PO
Clotrimazole|Canesten|1%|Cream|Antifungal|Apply a thin layer|BD|-|14|TOP
Ketoconazole|Nizoral|2%|Cream|Antifungal|Apply a thin layer|BD|-|14|TOP
Omeprazole|Losec / Risek|20 mg|Capsule|Acid reducer|1 capsule|M|B|14|PO
Esomeprazole|Nexium / Esoz|40 mg|Tablet|Acid reducer|1 tablet|M|B|14|PO
Pantoprazole|Controloc / Pantop|40 mg|Tablet|Acid reducer|1 tablet|M|B|14|PO
Rabeprazole|Pariet / Rabe|20 mg|Tablet|Acid reducer|1 tablet|M|B|14|PO
Esomeprazole|Nexum / Maxpro|40 mg|Injection|Acid reducer|1 vial|BD|-|3|IV
Ranitidine|Zantac / Neoceptin R|150 mg|Tablet|Acid reducer|1 tablet|BD|-|14|PO
Famotidine|Pepcid|20 mg|Tablet|Acid reducer|1 tablet|BD|-|14|PO
Antacid (aluminium + magnesium)|Gaviscon / Mylanta|10 ml|Syrup|Antacid|10 ml|TDS|A|7|PO
Domperidone|Motilium / Domstal|10 mg|Tablet|Nausea and vomiting|1 tablet|TDS|B|5|PO
Ondansetron|Zofran / Emistat|4 mg|Tablet|Nausea and vomiting|1 tablet|TDS|-|3|PO
Ondansetron|Zofran|4 mg/2 ml|Injection|Nausea and vomiting|1 ampoule|STAT|-|1|IV
Metoclopramide|Maxolon|10 mg|Tablet|Nausea and vomiting|1 tablet|TDS|B|3|PO
Hyoscine butylbromide|Buscopan|10 mg|Tablet|Stomach cramps|1 tablet|TDS|-|3|PO
Mebeverine|Colofac|135 mg|Tablet|Stomach cramps|1 tablet|TDS|B|7|PO
Loperamide|Imodium|2 mg|Capsule|Diarrhoea|2 capsules first, then 1|PRN|-|2|PO
Oral rehydration salts|ORS / Orsaline-N|20.5 g sachet|Sachet|Rehydration|1 sachet in 1 litre water|PRN|-|3|PO
Zinc sulphate|Zinc / Zinkid|20 mg|Tablet|Diarrhoea (children)|1 tablet|M|-|10|PO
Racecadotril|Hidrasec|100 mg|Capsule|Diarrhoea|1 capsule|TDS|B|3|PO
Lactulose|Duphalac|10 g/15 ml|Syrup|Constipation|15 ml|N|-|7|PO
Bisacodyl|Dulcolax|5 mg|Tablet|Constipation|2 tablets|N|-|3|PO
Ispaghula husk|Fybogel / Metamucil|3.5 g|Sachet|Constipation|1 sachet|BD|-|7|PO
Albendazole|Zentel / Alben|400 mg|Tablet|Worms|1 tablet|STAT|-|1|PO
Mebendazole|Vermox|100 mg|Tablet|Worms|1 tablet|BD|-|3|PO
Cetirizine|Zyrtec / Alatrol|10 mg|Tablet|Allergy|1 tablet|N|-|5|PO
Levocetirizine|Xyzal / Levocet|5 mg|Tablet|Allergy|1 tablet|N|-|5|PO
Loratadine|Clarityn / Lorfast|10 mg|Tablet|Allergy|1 tablet|M|-|5|PO
Fexofenadine|Telfast / Fexo|120 mg|Tablet|Allergy|1 tablet|M|-|5|PO
Chlorpheniramine|Piriton|4 mg|Tablet|Allergy|1 tablet|TDS|-|3|PO
Montelukast|Singulair / Montair|10 mg|Tablet|Asthma and allergy|1 tablet|N|-|14|PO
Salbutamol|Ventolin|100 mcg|Inhaler|Asthma|2 puffs|PRN|-|0|INH
Salbutamol|Ventolin|2 mg/5 ml|Syrup|Asthma|5 ml|TDS|-|5|PO
Salbutamol|Ventolin|2.5 mg/2.5 ml|Other|Asthma|1 respule|Q6|-|3|INH
Budesonide|Pulmicort|200 mcg|Inhaler|Asthma|1 puff|BD|-|0|INH
Salmeterol + fluticasone|Seretide|25/125 mcg|Inhaler|Asthma|2 puffs|BD|-|0|INH
Ipratropium|Atrovent|250 mcg/ml|Other|Asthma|1 respule|Q8|-|3|INH
Prednisolone|Deltacortril|5 mg|Tablet|Steroid|2 tablets|M|A|5|PO
Prednisolone|Predni|20 mg|Tablet|Steroid|1 tablet|M|A|5|PO
Dexamethasone|Decadron|4 mg/ml|Injection|Steroid|1 ampoule|STAT|-|1|IV
Hydrocortisone|Solu-Cortef|100 mg|Injection|Steroid|1 vial|STAT|-|1|IV
Betamethasone cream|Betnovate|0.1%|Cream|Steroid for skin|Apply a thin layer|BD|-|7|TOP
Mometasone cream|Elocon|0.1%|Cream|Steroid for skin|Apply a thin layer|M|-|7|TOP
Ambroxol|Mucosolvan|30 mg|Tablet|Cough and phlegm|1 tablet|TDS|A|5|PO
Ambroxol|Mucosolvan|15 mg/5 ml|Syrup|Cough and phlegm|5 ml|TDS|A|5|PO
Bromhexine|Bisolvon|4 mg|Tablet|Cough and phlegm|1 tablet|TDS|A|5|PO
Dextromethorphan|Benylin DM|15 mg/5 ml|Syrup|Dry cough|5 ml|TDS|-|5|PO
Pseudoephedrine + triprolidine|Actifed|60 mg + 2.5 mg|Tablet|Cold and blocked nose|1 tablet|TDS|-|3|PO
Xylometazoline|Otrivin|0.1%|Nasal spray|Blocked nose|1 spray each side|BD|-|3|NAS
Saline nasal drops|Sterimar / Salinase|0.9%|Drops|Blocked nose|2 drops each side|TDS|-|5|NAS
Metformin|Glucophage / Metfor|500 mg|Tablet|Diabetes|1 tablet|BD|W|0|PO
Metformin|Glucophage XR|1000 mg|Tablet|Diabetes|1 tablet|N|W|0|PO
Gliclazide|Diamicron / Glicron|80 mg|Tablet|Diabetes|1 tablet|M|B|0|PO
Glimepiride|Amaryl / Glimep|2 mg|Tablet|Diabetes|1 tablet|M|B|0|PO
Sitagliptin|Januvia|100 mg|Tablet|Diabetes|1 tablet|M|-|0|PO
Vildagliptin|Galvus|50 mg|Tablet|Diabetes|1 tablet|BD|-|0|PO
Empagliflozin|Jardiance|10 mg|Tablet|Diabetes|1 tablet|M|-|0|PO
Insulin glargine|Lantus|100 IU/ml|Injection|Diabetes (insulin)|Dose as advised|N|-|0|SC
Insulin human mixed 30/70|Mixtard / Humulin 70/30|100 IU/ml|Injection|Diabetes (insulin)|Dose as advised|BD|B|0|SC
Amlodipine|Norvasc / Amlo|5 mg|Tablet|Blood pressure|1 tablet|M|-|0|PO
Losartan|Cozaar / Losar|50 mg|Tablet|Blood pressure|1 tablet|M|-|0|PO
Telmisartan|Micardis / Telma|40 mg|Tablet|Blood pressure|1 tablet|M|-|0|PO
Valsartan|Diovan|80 mg|Tablet|Blood pressure|1 tablet|M|-|0|PO
Enalapril|Renitec|5 mg|Tablet|Blood pressure|1 tablet|BD|-|0|PO
Lisinopril|Zestril|10 mg|Tablet|Blood pressure|1 tablet|M|-|0|PO
Bisoprolol|Concor|5 mg|Tablet|Blood pressure and heart|1 tablet|M|-|0|PO
Atenolol|Tenormin|50 mg|Tablet|Blood pressure and heart|1 tablet|M|-|0|PO
Metoprolol|Betaloc|50 mg|Tablet|Blood pressure and heart|1 tablet|BD|-|0|PO
Carvedilol|Dilatrend|6.25 mg|Tablet|Heart failure and blood pressure|1 tablet|BD|W|0|PO
Hydrochlorothiazide|Esidrex|25 mg|Tablet|Water tablet|1 tablet|M|-|0|PO
Furosemide|Lasix|40 mg|Tablet|Water tablet|1 tablet|M|-|0|PO
Furosemide|Lasix|20 mg/2 ml|Injection|Water tablet|1 ampoule|STAT|-|1|IV
Spironolactone|Aldactone|25 mg|Tablet|Water tablet|1 tablet|M|-|0|PO
Atorvastatin|Lipitor / Atorva|20 mg|Tablet|Cholesterol|1 tablet|N|-|0|PO
Rosuvastatin|Crestor / Rosuvas|10 mg|Tablet|Cholesterol|1 tablet|N|-|0|PO
Aspirin|Disprin / Ecosprin|75 mg|Tablet|Blood thinner|1 tablet|M|A|0|PO
Clopidogrel|Plavix / Clopid|75 mg|Tablet|Blood thinner|1 tablet|M|-|0|PO
Warfarin|Warfarin|5 mg|Tablet|Blood thinner|As per INR|N|-|0|PO
Isosorbide mononitrate|Imdur|20 mg|Tablet|Angina|1 tablet|BD|-|0|PO
Glyceryl trinitrate|Angised|0.5 mg|Tablet|Angina|1 tablet under the tongue|PRN|-|0|SL
Digoxin|Lanoxin|0.25 mg|Tablet|Heart rhythm|1 tablet|M|-|0|PO
Levothyroxine|Euthyrox / Thyronorm|50 mcg|Tablet|Thyroid|1 tablet|M|E|0|PO
Carbimazole|Neo-Mercazole|5 mg|Tablet|Thyroid|1 tablet|TDS|-|0|PO
Allopurinol|Zyloric|100 mg|Tablet|Gout|1 tablet|M|A|0|PO
Colchicine|Colcrys|0.5 mg|Tablet|Gout|1 tablet|BD|A|5|PO
Febuxostat|Feburic|40 mg|Tablet|Gout|1 tablet|M|-|0|PO
Alendronate|Fosamax|70 mg|Tablet|Bone strength|1 tablet|WK|E|0|PO
Calcium + vitamin D3|Caltrate / Calbo-D|500 mg + 200 IU|Tablet|Bone strength|1 tablet|BD|A|0|PO
Vitamin D3|Sunny-D / D-Rise|50,000 IU|Capsule|Vitamin|1 capsule|WK|A|8|PO
Vitamin D3|Calcirol|200,000 IU|Injection|Vitamin|1 ampoule|STAT|-|1|IM
Vitamin B complex|Neurobion / Becosules|-|Capsule|Vitamin|1 capsule|M|A|30|PO
Methylcobalamin|Methycobal / Nuro-B|500 mcg|Tablet|Vitamin|1 tablet|BD|A|30|PO
Multivitamin and minerals|Centrum / Surbex-Z|-|Tablet|Vitamin|1 tablet|M|A|30|PO
Ferrous sulphate + folic acid|Ferrograd-F / Feroglobin|150 mg + 0.5 mg|Capsule|Iron for anaemia|1 capsule|M|A|60|PO
Ferrous fumarate|Fefol / Ferose|200 mg|Tablet|Iron for anaemia|1 tablet|BD|A|60|PO
Iron sucrose|Venofer|100 mg/5 ml|Injection|Iron for anaemia|1 ampoule|STAT|-|1|IV
Folic acid|Folic acid|5 mg|Tablet|Vitamin|1 tablet|M|-|30|PO
Vitamin C|Celin|500 mg|Tablet|Vitamin|1 tablet|M|-|14|PO
Vitamin K|Konakion|10 mg/ml|Injection|Vitamin|1 ampoule|STAT|-|1|IM
Tranexamic acid|Cyklokapron / Transamin|500 mg|Tablet|Bleeding|1 tablet|TDS|-|5|PO
Mefenamic acid + tranexamic acid|Ponstan + Transamin|-|Tablet|Heavy periods|1 tablet|TDS|A|5|PO
Norethisterone|Primolut-N|5 mg|Tablet|Periods|1 tablet|TDS|-|10|PO
Progesterone|Duphaston / Utrogestan|10 mg|Tablet|Women's health|1 tablet|BD|-|10|PO
Clomiphene|Clomid|50 mg|Tablet|Fertility|1 tablet|M|-|5|PO
Metronidazole + miconazole|Gynazole / Flagyl vaginal|-|Suppository|Vaginal infection|1 pessary|N|-|7|PR
Combined oral contraceptive|Marvelon / Femicon|-|Tablet|Contraception|1 tablet|N|-|0|PO
Amitriptyline|Tryptanol|10 mg|Tablet|Nerve pain and mood|1 tablet|N|-|14|PO
Sertraline|Zoloft / Serta|50 mg|Tablet|Depression and anxiety|1 tablet|M|-|0|PO
Escitalopram|Cipralex / Lexapro|10 mg|Tablet|Depression and anxiety|1 tablet|M|-|0|PO
Alprazolam|Xanax / Zolax|0.5 mg|Tablet|Anxiety|1 tablet|N|-|14|PO
Clonazepam|Rivotril|0.5 mg|Tablet|Anxiety and fits|1 tablet|N|-|14|PO
Zolpidem|Stilnox|10 mg|Tablet|Sleep|1 tablet|S|-|7|PO
Sodium valproate|Epilim|500 mg|Tablet|Epilepsy|1 tablet|BD|-|0|PO
Carbamazepine|Tegretol|200 mg|Tablet|Epilepsy and nerve pain|1 tablet|BD|-|0|PO
Levetiracetam|Keppra|500 mg|Tablet|Epilepsy|1 tablet|BD|-|0|PO
Cinnarizine|Stugeron|25 mg|Tablet|Dizziness|1 tablet|TDS|A|7|PO
Betahistine|Serc|16 mg|Tablet|Dizziness|1 tablet|TDS|W|14|PO
Sumatriptan|Imigran|50 mg|Tablet|Migraine|1 tablet|PRN|-|0|PO
Propranolol|Inderal|20 mg|Tablet|Migraine and heart rate|1 tablet|BD|-|14|PO
Cyclobenzaprine|Flexeril|10 mg|Tablet|Muscle spasm|1 tablet|N|-|7|PO
Tizanidine|Sirdalud|2 mg|Tablet|Muscle spasm|1 tablet|TDS|-|7|PO
Thiocolchicoside|Muscoril|4 mg|Capsule|Muscle spasm|1 capsule|BD|A|5|PO
Tamsulosin|Flomax / Urimax|0.4 mg|Capsule|Prostate and urine flow|1 capsule|N|A|0|PO
Sildenafil|Viagra|50 mg|Tablet|Erectile dysfunction|1 tablet|PRN|-|0|PO
Oxybutynin|Ditropan|5 mg|Tablet|Bladder|1 tablet|BD|-|14|PO
Potassium citrate|Urocit-K / Citralka|-|Syrup|Kidney stones|10 ml|TDS|-|14|PO
Artemether + lumefantrine|Coartem|20/120 mg|Tablet|Malaria|4 tablets|BD|W|3|PO
Chloroquine|Aralen|250 mg|Tablet|Malaria|4 tablets first, then 2|STAT|-|3|PO
Isoniazid + rifampicin + pyrazinamide + ethambutol|Rimstar / Akurit-4|-|Tablet|Tuberculosis|By weight|M|E|0|PO
Acyclovir|Zovirax|400 mg|Tablet|Antiviral|1 tablet|TDS|-|5|PO
Oseltamivir|Tamiflu|75 mg|Capsule|Antiviral|1 capsule|BD|-|5|PO
Permethrin|Lyclear / Permethrin|5%|Cream|Scabies and lice|Apply to the whole body|STAT|-|1|TOP
Calamine|Calamine|-|Lotion|Skin itch|Apply|TDS|-|7|TOP
Mupirocin|Bactroban|2%|Ointment|Skin infection|Apply a thin layer|TDS|-|7|TOP
Fusidic acid|Fucidin|2%|Cream|Skin infection|Apply a thin layer|TDS|-|7|TOP
Silver sulfadiazine|Flamazine|1%|Cream|Burns|Apply to the burn|BD|-|14|TOP
Povidone iodine|Betadine|10%|Other|Antiseptic|Apply|BD|-|7|TOP
Moisturiser (emollient)|Cetaphil / Aveeno|-|Lotion|Dry skin|Apply|BD|-|0|TOP
Tobramycin|Tobrex|0.3%|Eye drops|Eye infection|1 drop|QDS|-|7|EYE
Moxifloxacin|Vigamox|0.5%|Eye drops|Eye infection|1 drop|QDS|-|7|EYE
Carboxymethylcellulose|Refresh Tears / Tears Naturale|0.5%|Eye drops|Dry eye|1 drop|QDS|-|0|EYE
Timolol|Timoptol|0.5%|Eye drops|Glaucoma|1 drop|BD|-|0|EYE
Ciprofloxacin|Ciloxan|0.3%|Ear drops|Ear infection|3 drops|TDS|-|7|EAR
Ofloxacin + clotrimazole + beclomethasone|Otosporin / Candid-B|-|Ear drops|Ear infection|3 drops|TDS|-|7|EAR
Chlorhexidine|Corsodyl / Hexidine|0.2%|Other|Gum and mouth care|10 ml rinse|BD|-|7|LOC
Benzydamine|Difflam|0.15%|Other|Mouth and throat pain|15 ml gargle|TDS|-|5|LOC
Choline salicylate|Bonjela|8.7%|Gel|Mouth ulcers|Apply|TDS|-|7|LOC
Lidocaine|Xylocaine|2%|Injection|Local anaesthetic|As required|STAT|-|1|SC
Normal saline|Normal saline|0.9% 500 ml|Infusion|Drip|500 ml|STAT|-|1|IV
Ringer's lactate|Ringer's lactate|500 ml|Infusion|Drip|500 ml|STAT|-|1|IV
Dextrose|Dextrose 5%|500 ml|Infusion|Drip|500 ml|STAT|-|1|IV
Adrenaline|Adrenaline|1 mg/ml|Injection|Emergency|0.5 ml|STAT|-|1|IM
Hydroxyzine|Atarax|25 mg|Tablet|Allergy and itch|1 tablet|N|-|7|PO
TXT;

$out = [];
foreach (explode("\n", trim($raw)) as $line) {
    $p = array_map('trim', explode('|', $line));
    if (count($p) < 10) {
        continue;
    }
    [$gen, $brand, $str, $form, $class, $dose, $f, $w, $days, $r] = $p;
    $out[] = ['generic' => $gen, 'brand' => $brand, 'strength' => $str === '-' ? '' : $str, 'form' => $form, 'class' => $class, 'dose' => $dose,
        'freq' => $freq[$f] ?? '', 'timing' => $when[$w] ?? '', 'dur_n' => (int) $days > 0 ? (int) $days : null, 'dur_unit' => (int) $days > 0 ? 'days' : 'ongoing', 'route' => $route[$r] ?? ''];
}
return $out;
