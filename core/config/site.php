<?php
// Selfcare original code. Default content of the main website and the footer.
// The super admin can change all of this from the portal (Website content).
// Footer link columns are defined here in one place: edit the arrays below to change them.
declare(strict_types=1);

return [
    'settings' => [
        'site_name' => 'Self Care',
        'tagline' => 'One home for every healthcare provider',
        'hero_badge' => 'Built for clinics, laboratories, hospitals, pharmacies and more',
        'hero_title' => 'Run your healthcare business from one calm place',
        'hero_title_accent' => 'from one calm place',
        'hero_text' => 'Self Care gives every hospital, clinic, laboratory, pharmacy and care provider its own website, its own portal for staff, and simple plans that grow with you.',
        'hero_cta' => 'Register your organisation',
        'who_title' => 'Who we serve',
        'who_text' => 'Pick the kind of healthcare business you run. Each one gets a website and a portal made for the way it works.',
        'directory_title' => 'Find a healthcare provider',
        'directory_text' => 'Every provider below runs on Self Care. Open a website to see services, book or contact them.',
        'features_title' => 'Everything your clinic or laboratory runs on',
        'steps_title' => 'Start in three simple steps',
        'pricing_title' => 'Simple plans that grow with you',
        'pricing_text' => 'Start free. Move up when you need more branches, staff or tools. Choose Monthly or Yearly.',
        'faq_title' => 'Questions people ask',
        'free_title' => 'Free services near you',
        'free_text' => 'Free health camps, free delivery, free sample collection and more, offered by providers on Self Care.',
        'cta_title' => 'Ready to bring your organisation online?',
        'cta_text' => 'Create your account in a minute. Your website goes live on its own address right away.',
        'contact_email' => '', 'contact_phone' => '', 'contact_address' => '',
        'social_facebook' => '', 'social_x' => '', 'social_linkedin' => '', 'social_youtube' => '', 'social_instagram' => '',
        'footer_note' => 'One calm workspace for every healthcare team.',
        'newsletter_text' => 'Get product news. No spam.',
        'seo_title' => 'Self Care: websites and portals for hospitals, clinics, labs and pharmacies',
        'seo_description' => 'Self Care gives every healthcare provider a website, a staff portal and simple subscription plans.',
        'logo' => '',
        'default_plan_id' => '',
    ],
    'features' => [
        ['title' => 'Your own website', 'body' => 'Every provider gets a fast website on its own address: services, departments, doctors, branches with addresses, online requests and a patient sign in.', 'meta' => 'globe'],
        ['title' => 'Clinic and laboratory suite', 'body' => 'Patients, test booking, samples, results, pathologist approval, printed reports, sharing by email and WhatsApp, and home collection.', 'meta' => 'flask'],
        ['title' => 'Branches that run on their own', 'body' => 'Add branches any time. Each has its own admin and staff, its own data, and its own currency and time zone, while the head office sees everything.', 'meta' => 'building'],
        ['title' => 'Finance and commission', 'body' => 'Every income and expense as a line, doctor and partner laboratory commission, refunds, discounts and net income, with clear totals by period and branch.', 'meta' => 'chart'],
        ['title' => 'Staff, attendance and salary', 'body' => 'Staff profiles, daily attendance with a private PIN, salary sheets, advances, deductions and printable salary slips.', 'meta' => 'users'],
        ['title' => 'Stock and inventory', 'body' => 'Items, batches, low stock and expiry alerts, stock movements, transfers and the value of what you hold.', 'meta' => 'box'],
        ['title' => 'Roles, security and backups', 'body' => 'A role for every job, extra rights for single people, a record of who did what, secure sign in and backups you can download.', 'meta' => 'shield'],
        ['title' => 'Plans you control', 'body' => 'Start free. Limits, length and modules are set per plan and can change at any time. Prices show in your own currency.', 'meta' => 'card'],
    ],
    'steps' => [
        ['title' => 'Register', 'body' => 'Choose what you are, such as a clinic, laboratory, hospital or pharmacy, name your organisation and pick your web address.'],
        ['title' => 'Set up', 'body' => 'Add your services, branches, team and opening hours from your portal. No code needed.'],
        ['title' => 'Go live', 'body' => 'Your website is live on its own address. People can find you and send requests the same day.'],
    ],
    'faq' => [
        ['title' => 'Who can register?', 'body' => 'Any healthcare provider: hospitals, clinics, laboratories, pharmacies, blood banks, ambulance services, nursing homes, home care and more.'],
        ['title' => 'Is there a free plan?', 'body' => 'Yes. The Free plan costs nothing and does not expire. Paid plans add more people, branches and tools.'],
        ['title' => 'Can I pay monthly or yearly?', 'body' => 'Yes. Choose Monthly or Yearly on the pricing section. Yearly costs less than twelve months.'],
        ['title' => 'Do I get my own address?', 'body' => 'Yes. Each provider gets its own website address, and the main site lists you in the directory.'],
        ['title' => 'Can I run several branches?', 'body' => 'Yes. Clinics and laboratories can add branches, give each branch its own admin and staff, and set a currency and time zone for each branch.'],
        ['title' => 'Can my staff have limited access?', 'body' => 'Yes. You choose what each role can do, and you can give access for a set period or only during a shift.'],
        ['title' => 'How do my staff and patients sign in?', 'body' => 'Everyone signs in on one page, the same Sign in page. After sign in each person lands in the right place for their role and branch.'],
    ],
    'testimonials' => [],

    // Footer: columns of links. 'url' starting with / is inside this site. Edit freely.
    'footer_columns' => [
        'Product' => [['Features', '/#features'], ['Who we serve', '/#who'], ['Pricing', '/#pricing'], ['Register', '/register'], ['Sign in', '/login']],
        'Discover' => [['Provider directory', '/directory'], ['Free services', '/free-services'], ['How it works', '/#how']],
        'Company' => [['About Self Care', '/#features'], ['Contact', '/#contact'], ['Questions', '/#faq']],
        'Legal' => [['Terms of use', '/terms'], ['Privacy policy', '/privacy']],
    ],
];
