<?php
/**
 * Richfield Student Accommodation — all site data.
 *
 * ######################################################################
 * ###  [DB-1]  DATABASE INTEGRATION POINT — START HERE               ###
 * ######################################################################
 *
 * THIS WHOLE FILE IS THE DATABASE LAYER, currently faked with PHP arrays.
 * Replacing it is the main job. Nothing else in the site reads these
 * arrays directly — every page goes through the lookup functions in
 * functions.php — so if those functions keep returning the same shapes,
 * the rest of the site needs no changes at all.
 *
 * Search the project for "[DB-" to find every integration point. There
 * are seven, numbered DB-1 to DB-7, and README.md lists them.
 *
 * SUGGESTED ORDER OF WORK
 *   1. Add your connection file (see [DB-2] in functions.php).
 *   2. Replace the lookup functions ([DB-3]) so they query instead of
 *      reading these arrays. The site keeps working at this point.
 *   3. Move search filtering into SQL ([DB-5]) for performance.
 *   4. Save viewing requests ([DB-6]) — the highest-value one.
 *   5. Optionally persist shortlists ([DB-7]).
 *
 * THE SHAPE THE CODE ALREADY EXPECTS
 * These are the keys every page reads. Your tables need to be able to
 * produce them, whatever you name the columns:
 *
 *   campus    : id, name, city, province, lat, lng, confirmed, beds
 *   listing   : slug (unique), name, area, campus, lat, lng, rent,
 *               room, beds, amenities (many-to-many), verified, seed,
 *               blurb
 *   amenity   : id, short, label
 *   room type : id, label
 *   faq       : q, a  (plus a sort order once they are rows)
 *
 * `rent` is whole rand, stored as an integer. Keep it that way — the
 * calculator in functions.php rounds to whole rand throughout, and a
 * float here would introduce cent-level drift.
 *
 * DO NOT DROP: the `confirmed` flag on campuses and the `verified` flag
 * on listings both drive visible honesty markers in the UI.
 *
 * ######################################################################
 *
 * =========================== SAMPLE INVENTORY ===========================
 * Every building name below is INVENTED and every area is suburb-level.
 * No real landlord, business identity or street address appears here.
 * This is demonstration data, not real availability. Replace it wholesale
 * before this is used commercially, and set SAMPLE_DATA to false to hide
 * the banner.
 * ======================================================================== */

const SAMPLE_DATA = true;

/**
 * VERIFICATION STATUS
 * Bryanston, Pretoria and Umhlanga are confirmed on richfield.ac.za/campuses.
 * The other five came from a third-party directory and are marked
 * 'confirmed' => false, which renders a "Location to confirm" badge.
 *
 * >>> CONFIRM THOSE FIVE WITH THE CLIENT BEFORE PRESENTING.
 *
 * Coordinates are suburb-level approximations, not surveyed campus
 * positions. Bed counts are illustrative.
 */
const CAMPUSES = [
    'bryanston' => ['name' => 'Bryanston',        'city' => 'Johannesburg', 'province' => 'Gauteng',       'lat' => -26.0561, 'lng' => 28.0184, 'confirmed' => true,  'beds' => 148],
    'newtown'   => ['name' => 'Newtown Junction', 'city' => 'Johannesburg', 'province' => 'Gauteng',       'lat' => -26.2027, 'lng' => 28.0334, 'confirmed' => false, 'beds' => 96],
    'centurion' => ['name' => 'Centurion',        'city' => 'Centurion',    'province' => 'Gauteng',       'lat' => -25.8603, 'lng' => 28.1894, 'confirmed' => false, 'beds' => 74],
    'pretoria'  => ['name' => 'Pretoria',         'city' => 'Pretoria',     'province' => 'Gauteng',       'lat' => -25.7479, 'lng' => 28.2293, 'confirmed' => true,  'beds' => 132],
    'umhlanga'  => ['name' => 'Umhlanga',         'city' => 'Durban',       'province' => 'KwaZulu-Natal', 'lat' => -29.7268, 'lng' => 31.0839, 'confirmed' => true,  'beds' => 118],
    'musgrave'  => ['name' => 'Musgrave',         'city' => 'Durban',       'province' => 'KwaZulu-Natal', 'lat' => -29.8462, 'lng' => 31.0044, 'confirmed' => false, 'beds' => 88],
    'cape-town' => ['name' => 'Cape Town',        'city' => 'Cape Town',    'province' => 'Western Cape',  'lat' => -33.9249, 'lng' => 18.4241, 'confirmed' => false, 'beds' => 104],
    'polokwane' => ['name' => 'Polokwane',        'city' => 'Polokwane',    'province' => 'Limpopo',       'lat' => -23.9045, 'lng' => 29.4689, 'confirmed' => false, 'beds' => 62],
];

const DEFAULT_CAMPUS = 'bryanston';

/** Amenity taxonomy. 'icon' values are inline SVG keys resolved in functions.php. */
const AMENITIES = [
    'uncapped-wifi'       => ['short' => 'Uncapped Wi-Fi',      'label' => 'Uncapped fibre Wi-Fi'],
    'backup-power'        => ['short' => 'Backup power',        'label' => 'Backup power during outages'],
    'biometric-access'    => ['short' => 'Biometric access',    'label' => 'Biometric access control'],
    '24h-security'        => ['short' => '24/7 security',       'label' => '24/7 on-site security'],
    'laundry'             => ['short' => 'Laundry',             'label' => 'On-site laundry'],
    'study-room'          => ['short' => 'Study rooms',         'label' => 'Dedicated study rooms'],
    'shuttle'             => ['short' => 'Campus shuttle',      'label' => 'Campus shuttle service'],
    'prepaid-electricity' => ['short' => 'Prepaid electricity', 'label' => 'Prepaid electricity'],
    'furnished'           => ['short' => 'Furnished',           'label' => 'Fully furnished'],
    'cleaning'            => ['short' => 'Weekly cleaning',     'label' => 'Weekly communal cleaning'],
];

const ROOM_TYPES = [
    'single'    => 'Single room',
    'sharing-2' => 'Sharing (2)',
    'sharing-3' => 'Sharing (3)',
    'studio'    => 'Studio',
    'en-suite'  => 'En-suite',
];

/** 18 sample listings, keyed by slug. */
const LISTINGS = [
    'acacia-court-bryanston' => [
        'name' => 'Acacia Court', 'area' => 'Bryanston, Sandton', 'campus' => 'bryanston',
        'lat' => -26.0508, 'lng' => 28.0231, 'rent' => 5200, 'room' => 'en-suite', 'beds' => 6,
        'amenities' => ['uncapped-wifi', 'backup-power', '24h-security', 'furnished', 'study-room'],
        'verified' => true, 'seed' => 'acacia',
        'blurb' => 'A quiet walk-up complex with a dedicated study lounge and a generator that carries the whole building through outages.',
    ],
    'the-jacaranda-rooms' => [
        'name' => 'The Jacaranda Rooms', 'area' => 'Epsom Downs', 'campus' => 'bryanston',
        'lat' => -26.0662, 'lng' => 28.0098, 'rent' => 3900, 'room' => 'sharing-2', 'beds' => 11,
        'amenities' => ['uncapped-wifi', 'laundry', 'prepaid-electricity', 'furnished'],
        'verified' => true, 'seed' => 'jacaranda',
        'blurb' => 'Shared twin rooms with weekly linen service and a large communal kitchen. The cheapest verified option near Bryanston.',
    ],
    'sandalwood-studios' => [
        'name' => 'Sandalwood Studios', 'area' => 'Morningside Manor', 'campus' => 'bryanston',
        'lat' => -26.0459, 'lng' => 28.0402, 'rent' => 7100, 'room' => 'studio', 'beds' => 3,
        'amenities' => ['uncapped-wifi', 'backup-power', 'biometric-access', '24h-security', 'furnished', 'cleaning'],
        'verified' => true, 'seed' => 'sandalwood',
        'blurb' => 'Self-contained studios with private kitchenettes. Suited to postgraduate students who want to live alone.',
    ],
    'market-square-residence' => [
        'name' => 'Market Square Residence', 'area' => 'Newtown', 'campus' => 'newtown',
        'lat' => -26.1988, 'lng' => 28.0289, 'rent' => 4300, 'room' => 'single', 'beds' => 14,
        'amenities' => ['uncapped-wifi', '24h-security', 'biometric-access', 'laundry', 'furnished'],
        'verified' => true, 'seed' => 'marketsq',
        'blurb' => 'A converted warehouse building with controlled access and a rooftop common area. Five minutes from campus on foot.',
    ],
    'foundry-lofts' => [
        'name' => 'Foundry Lofts', 'area' => 'Ferreirasdorp', 'campus' => 'newtown',
        'lat' => -26.2094, 'lng' => 28.0398, 'rent' => 3400, 'room' => 'sharing-3', 'beds' => 18,
        'amenities' => ['uncapped-wifi', 'prepaid-electricity', 'laundry', 'shuttle'],
        'verified' => false, 'seed' => 'foundry',
        'blurb' => 'Budget three-sharing rooms with a scheduled shuttle to campus each morning.',
    ],
    'highveld-park-lodge' => [
        'name' => 'Highveld Park Lodge', 'area' => 'Highveld, Centurion', 'campus' => 'centurion',
        'lat' => -25.8672, 'lng' => 28.1801, 'rent' => 4800, 'room' => 'single', 'beds' => 8,
        'amenities' => ['uncapped-wifi', 'backup-power', '24h-security', 'furnished', 'study-room', 'cleaning'],
        'verified' => true, 'seed' => 'highveld',
        'blurb' => 'Single rooms in a gated estate with a quiet study block and weekly cleaning of shared areas.',
    ],
    'the-lyttelton-house' => [
        'name' => 'The Lyttelton House', 'area' => 'Lyttelton Manor', 'campus' => 'centurion',
        'lat' => -25.8541, 'lng' => 28.2003, 'rent' => 3600, 'room' => 'sharing-2', 'beds' => 9,
        'amenities' => ['uncapped-wifi', 'laundry', 'prepaid-electricity'],
        'verified' => false, 'seed' => 'lyttelton',
        'blurb' => 'A converted family home with six twin rooms and a large garden. Walking distance to the Centurion transport hub.',
    ],
    'arcadia-heights' => [
        'name' => 'Arcadia Heights', 'area' => 'Arcadia, Pretoria', 'campus' => 'pretoria',
        'lat' => -25.7431, 'lng' => 28.2211, 'rent' => 4100, 'room' => 'single', 'beds' => 16,
        'amenities' => ['uncapped-wifi', '24h-security', 'biometric-access', 'laundry', 'furnished', 'study-room'],
        'verified' => true, 'seed' => 'arcadia',
        'blurb' => 'Purpose-converted apartment block with a staffed reception and two study floors. The most popular Pretoria option.',
    ],
    'sunnyside-commons' => [
        'name' => 'Sunnyside Commons', 'area' => 'Sunnyside', 'campus' => 'pretoria',
        'lat' => -25.7562, 'lng' => 28.2118, 'rent' => 2800, 'room' => 'sharing-3', 'beds' => 21,
        'amenities' => ['uncapped-wifi', 'prepaid-electricity', 'laundry'],
        'verified' => false, 'seed' => 'sunnyside',
        'blurb' => 'The lowest rent in the Pretoria list. Three-sharing rooms, prepaid meters, communal kitchen on each floor.',
    ],
    'hatfield-quarter' => [
        'name' => 'Hatfield Quarter', 'area' => 'Hatfield', 'campus' => 'pretoria',
        'lat' => -25.7489, 'lng' => 28.2381, 'rent' => 6400, 'room' => 'en-suite', 'beds' => 5,
        'amenities' => ['uncapped-wifi', 'backup-power', 'biometric-access', '24h-security', 'furnished', 'cleaning', 'study-room'],
        'verified' => true, 'seed' => 'hatfield',
        'blurb' => 'En-suite rooms with backup power and biometric entry, in the busiest student precinct in Pretoria.',
    ],
    'la-lucia-residence' => [
        'name' => 'La Lucia Residence', 'area' => 'La Lucia', 'campus' => 'umhlanga',
        'lat' => -29.7368, 'lng' => 31.0721, 'rent' => 5600, 'room' => 'single', 'beds' => 7,
        'amenities' => ['uncapped-wifi', 'backup-power', '24h-security', 'furnished', 'shuttle'],
        'verified' => true, 'seed' => 'lalucia',
        'blurb' => 'Single rooms a short shuttle ride from the Umhlanga campus, with backup power through load reduction.',
    ],
    'the-ridge-rooms' => [
        'name' => 'The Ridge Rooms', 'area' => 'Umhlanga Ridge', 'campus' => 'umhlanga',
        'lat' => -29.7301, 'lng' => 31.0762, 'rent' => 7500, 'room' => 'studio', 'beds' => 2,
        'amenities' => ['uncapped-wifi', 'backup-power', 'biometric-access', '24h-security', 'furnished', 'cleaning', 'study-room'],
        'verified' => true, 'seed' => 'ridge',
        'blurb' => 'The most expensive listing on the platform. Serviced studios within walking distance of the campus.',
    ],
    'glen-ashley-house' => [
        'name' => 'Glen Ashley House', 'area' => 'Glen Ashley', 'campus' => 'umhlanga',
        'lat' => -29.7602, 'lng' => 31.0498, 'rent' => 3800, 'room' => 'sharing-2', 'beds' => 12,
        'amenities' => ['uncapped-wifi', 'laundry', 'prepaid-electricity', 'shuttle'],
        'verified' => false, 'seed' => 'glenashley',
        'blurb' => 'Twin-sharing rooms in a residential suburb, with a morning and afternoon shuttle.',
    ],
    'berea-park-lodge' => [
        'name' => 'Berea Park Lodge', 'area' => 'Berea', 'campus' => 'musgrave',
        'lat' => -29.8398, 'lng' => 31.0102, 'rent' => 4200, 'room' => 'single', 'beds' => 10,
        'amenities' => ['uncapped-wifi', '24h-security', 'laundry', 'furnished', 'study-room'],
        'verified' => true, 'seed' => 'berea',
        'blurb' => 'A long-established student house on the Berea ridge, ten minutes downhill to the Musgrave campus.',
    ],
    'essenwood-rooms' => [
        'name' => 'Essenwood Rooms', 'area' => 'Essenwood', 'campus' => 'musgrave',
        'lat' => -29.8511, 'lng' => 30.9987, 'rent' => 3200, 'room' => 'sharing-3', 'beds' => 15,
        'amenities' => ['uncapped-wifi', 'prepaid-electricity', 'laundry'],
        'verified' => false, 'seed' => 'essenwood',
        'blurb' => 'Three-sharing rooms with prepaid electricity and a shared courtyard.',
    ],
    'salt-river-works' => [
        'name' => 'Salt River Works', 'area' => 'Salt River', 'campus' => 'cape-town',
        'lat' => -33.9281, 'lng' => 18.4652, 'rent' => 5900, 'room' => 'single', 'beds' => 9,
        'amenities' => ['uncapped-wifi', 'backup-power', 'biometric-access', '24h-security', 'furnished', 'laundry'],
        'verified' => true, 'seed' => 'saltriver',
        'blurb' => 'A converted industrial building with controlled access, on the main transport line into the city centre.',
    ],
    'observatory-commons' => [
        'name' => 'Observatory Commons', 'area' => 'Observatory', 'campus' => 'cape-town',
        'lat' => -33.9372, 'lng' => 18.4718, 'rent' => 4400, 'room' => 'sharing-2', 'beds' => 13,
        'amenities' => ['uncapped-wifi', 'laundry', 'study-room', 'furnished'],
        'verified' => true, 'seed' => 'observatory',
        'blurb' => "Twin rooms in Cape Town's long-standing student suburb, with a shared study room and fast fibre.",
    ],
    'bendor-student-house' => [
        'name' => 'Bendor Student House', 'area' => 'Bendor', 'campus' => 'polokwane',
        'lat' => -23.8971, 'lng' => 29.4802, 'rent' => 3100, 'room' => 'single', 'beds' => 8,
        'amenities' => ['uncapped-wifi', '24h-security', 'prepaid-electricity', 'furnished', 'laundry'],
        'verified' => true, 'seed' => 'bendor',
        'blurb' => 'Single rooms in a secure residential complex, a short drive from the Polokwane campus.',
    ],
];

/**
 * Every answer must stay consistent with verified research about Richfield.
 * The NSFAS answer is deliberately first: Richfield is a PRIVATE institution
 * and its students are NOT NSFAS-eligible. Answering that head-on is more
 * credible than staying silent, and it is the one fact generic student-housing
 * templates always get wrong.
 */
const FAQS = [
    [
        'q' => 'Can I use NSFAS funding for this accommodation?',
        'a' => 'No. NSFAS funding is designated for public universities and TVET colleges, and Richfield is a private higher education institution, so Richfield students are not NSFAS-eligible. Richfield supports students through flexible payment plans, internal bursary opportunities, and partnerships with private financial institutions for loan financing. Your campus finance office can tell you which of these you qualify for.',
    ],
    [
        'q' => 'Does Richfield run its own residences?',
        'a' => 'Richfield does not operate internal residence halls. This portal lists independent accommodation providers near each campus so you can compare inspected options in one place, instead of contacting providers one at a time.',
    ],
    [
        'q' => 'What does an inspection actually check?',
        'a' => 'A listing marked Inspected has been physically visited. The check covers secure entry and perimeter, working locks on bedroom doors, water pressure and hot water, electrical safety and whether backup power exists, internet speed measured on site, cleanliness of shared kitchens and bathrooms, and the real walking route to campus rather than the straight-line distance.',
    ],
    [
        'q' => 'How much deposit will I need?',
        'a' => "Most providers ask for one month's rent as a refundable deposit, though some ask for two and a few ask for none. The deposit is held by the accommodation provider, not by Richfield and not by this platform. Use the cost calculator to see the full amount due before move-in.",
    ],
    [
        'q' => 'How long are the leases?',
        'a' => 'Providers typically offer 6-month, 10-month academic-year, and 12-month leases. A 10-month lease covers the academic year without paying through the December and January break, but tends to carry slightly higher monthly rent. Confirm the exact term with the provider at your viewing.',
    ],
    [
        'q' => 'What happens after I book a viewing?',
        'a' => 'Your request goes to the accommodation provider with your preferred date and the rooms you shortlisted. They contact you directly to confirm a time. Booking a viewing costs nothing, commits you to nothing, and you can book viewings at several places on the same day.',
    ],
    [
        'q' => 'Is there a fee to use this service?',
        'a' => 'No. Searching, shortlisting and booking viewings are free for students. There is no booking fee and no commission added to your rent.',
    ],
    [
        'q' => 'How safe are these areas?',
        'a' => 'Safety varies by area and we do not pretend otherwise. Every inspected listing records what security it actually has — 24/7 guarding, biometric or access-controlled entry, perimeter fencing — and those are shown on each listing rather than summarised into a single score. Visit in person before signing, ideally after dark as well as during the day.',
    ],
    [
        'q' => 'What if something is wrong with a listing?',
        'a' => 'Report it and we will re-inspect. If a listing turns out to be inaccurate about price, availability, or what is included, it is removed until the provider corrects it.',
    ],
    [
        'q' => 'Can my parent or guardian be involved?',
        'a' => 'Yes, and most are. Add their email when you request a viewing and they receive the same confirmation and lease summary you do. There is a section on the home page written specifically for parents and guardians.',
    ],
];
