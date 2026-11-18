<?php
// Selfcare original code. Default content of the main website and the footer.
// The super admin can change all of this from the portal (Website content).
// Footer link columns are defined here in one place: edit the arrays below to change them.
declare(strict_types=1);

return [
    'settings' => [
        'site_name' => 'Self Care',
        'tagline' => 'One home for every healthcare provider',
        'hero_badge' => 'Built for hospitals, clinics, labs, pharmacies and more',
        'hero_title' => 'Run your healthcare business from one calm place',
        'hero_title_accent' => 'from one calm place',
        'hero_text' => 'Self Care gives every hospital, clinic, laboratory, pharmacy and care provider its own website, its own portal for staff, and simple plans that grow with you.',
        'hero_cta' => 'Register your organisation',
        'who_title' => 'Who we serve',
        'who_text' => 'Pick the kind of healthcare business you run. Each one gets a website and a portal made for the way it works.',
        'directory_title' => 'Find a healthcare provider',
        'directory_text' => 'Every provider below runs on Self Care. Open a website to see services, book or contact them.',
        'features_title' => 'Everything you need, nothing you do not',
        'steps_title' => 'Start in three simple steps',
        'pricing_title' => 'Simple plans that grow with you',
        'pricing_text' => 'Start free. Move up when you need more doctors, patients or tools. Self Care can also give you a plan by hand.',
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
        ['title' => 'Your own website', 'body' => 'Every provider gets a fast website on its own address with services, people, hours and online requests.', 'meta' => 'globe'],
        ['title' => 'A portal for every role', 'body' => 'Admins, doctors, nurses, front desk, pharmacists, lab staff and patients each see only what they need.', 'meta' => 'users'],
        ['title' => 'Plans you control', 'body' => 'Free to start. Limits, length and modules are set per plan, and can be changed at any time.', 'meta' => 'card'],
        ['title' => 'Shift and temporary access', 'body' => 'Give staff access for a season or only during their shift. It stops by itself when time is up.', 'meta' => 'clock'],
        ['title' => 'Free services, front and centre', 'body' => 'Share free health camps, delivery or sample pickup so people can find them.', 'meta' => 'heart'],
        ['title' => 'Light and dark', 'body' => 'Every page works in both modes and on every screen size.', 'meta' => 'sun'],
    ],
    'steps' => [
        ['title' => 'Register', 'body' => 'Choose what you are (hospital, pharmacy, lab and so on), name your organisation and pick your web address.'],
        ['title' => 'Set up', 'body' => 'Add your services, team and opening hours from your portal. No code needed.'],
        ['title' => 'Go live', 'body' => 'Your website is live on its own address. People can find you and send requests the same day.'],
    ],
    'faq' => [
        ['title' => 'Who can register?', 'body' => 'Any healthcare provider: hospitals, clinics, laboratories, pharmacies, blood banks, ambulance services, nursing homes, home care and more.'],
        ['title' => 'Is there a free plan?', 'body' => 'Yes. The Free plan costs nothing and does not expire. Paid plans add more people and tools.'],
        ['title' => 'Do I get my own address?', 'body' => 'Yes. Each provider gets its own website address, and the main site lists you in the directory.'],
        ['title' => 'Can my staff have limited access?', 'body' => 'Yes. You choose what each role can do, and you can give access for a set period or only during a shift.'],
    ],
    'testimonials' => [
        ['title' => 'Asha Rahman', 'body' => 'We moved our clinic bookings online in one afternoon. Patients now find us and book without calling.', 'meta' => 'Clinic owner'],
        ['title' => 'Karim Hossain', 'body' => 'Our pharmacy lists products and delivery times, and orders arrive in one place.', 'meta' => 'Pharmacist'],
        ['title' => 'Dr. Nusrat Jahan', 'body' => 'Simple, quiet and clear. My team only sees what their role needs.', 'meta' => 'Hospital director'],
    ],

    // Footer: columns of links. 'url' starting with / is inside this site. Edit freely.
    'footer_columns' => [
        'Product' => [['Features', '/#features'], ['Who we serve', '/#who'], ['Pricing', '/#pricing'], ['Register', '/register'], ['Sign in', '/login']],
        'Discover' => [['Provider directory', '/directory'], ['Free services', '/free-services'], ['How it works', '/#how']],
        'Company' => [['About Self Care', '/#features'], ['Contact', '/#contact'], ['Questions', '/#faq']],
        'Legal' => [['Terms of use', '/terms'], ['Privacy policy', '/privacy']],
    ],
];
