<?php

/*
|--------------------------------------------------------------------------
| Countries & international dial codes
|--------------------------------------------------------------------------
|
| Drives the country-code selector on the registration form (and any other
| phone-entry UI). Each entry is [iso (alpha-2), name, dial]. The flag emoji
| is derived from the ISO code in the view, so it doesn't need storing here.
| `default` is the dial code pre-selected on the form.
|
*/

return [

    'default' => '+233',

    'list' => [
        ['iso' => 'GH', 'name' => 'Ghana', 'dial' => '+233'],
        ['iso' => 'NG', 'name' => 'Nigeria', 'dial' => '+234'],
        ['iso' => 'KE', 'name' => 'Kenya', 'dial' => '+254'],
        ['iso' => 'ZA', 'name' => 'South Africa', 'dial' => '+27'],
        ['iso' => 'UG', 'name' => 'Uganda', 'dial' => '+256'],
        ['iso' => 'TZ', 'name' => 'Tanzania', 'dial' => '+255'],
        ['iso' => 'RW', 'name' => 'Rwanda', 'dial' => '+250'],
        ['iso' => 'CI', 'name' => "Côte d'Ivoire", 'dial' => '+225'],
        ['iso' => 'SN', 'name' => 'Senegal', 'dial' => '+221'],
        ['iso' => 'CM', 'name' => 'Cameroon', 'dial' => '+237'],
        ['iso' => 'EG', 'name' => 'Egypt', 'dial' => '+20'],
        ['iso' => 'MA', 'name' => 'Morocco', 'dial' => '+212'],
        ['iso' => 'ET', 'name' => 'Ethiopia', 'dial' => '+251'],
        ['iso' => 'ZM', 'name' => 'Zambia', 'dial' => '+260'],
        ['iso' => 'ZW', 'name' => 'Zimbabwe', 'dial' => '+263'],
        ['iso' => 'US', 'name' => 'United States', 'dial' => '+1'],
        ['iso' => 'CA', 'name' => 'Canada', 'dial' => '+1'],
        ['iso' => 'GB', 'name' => 'United Kingdom', 'dial' => '+44'],
        ['iso' => 'IE', 'name' => 'Ireland', 'dial' => '+353'],
        ['iso' => 'FR', 'name' => 'France', 'dial' => '+33'],
        ['iso' => 'DE', 'name' => 'Germany', 'dial' => '+49'],
        ['iso' => 'ES', 'name' => 'Spain', 'dial' => '+34'],
        ['iso' => 'PT', 'name' => 'Portugal', 'dial' => '+351'],
        ['iso' => 'IT', 'name' => 'Italy', 'dial' => '+39'],
        ['iso' => 'NL', 'name' => 'Netherlands', 'dial' => '+31'],
        ['iso' => 'BE', 'name' => 'Belgium', 'dial' => '+32'],
        ['iso' => 'CH', 'name' => 'Switzerland', 'dial' => '+41'],
        ['iso' => 'SE', 'name' => 'Sweden', 'dial' => '+46'],
        ['iso' => 'NO', 'name' => 'Norway', 'dial' => '+47'],
        ['iso' => 'DK', 'name' => 'Denmark', 'dial' => '+45'],
        ['iso' => 'FI', 'name' => 'Finland', 'dial' => '+358'],
        ['iso' => 'PL', 'name' => 'Poland', 'dial' => '+48'],
        ['iso' => 'AT', 'name' => 'Austria', 'dial' => '+43'],
        ['iso' => 'GR', 'name' => 'Greece', 'dial' => '+30'],
        ['iso' => 'AE', 'name' => 'United Arab Emirates', 'dial' => '+971'],
        ['iso' => 'SA', 'name' => 'Saudi Arabia', 'dial' => '+966'],
        ['iso' => 'QA', 'name' => 'Qatar', 'dial' => '+974'],
        ['iso' => 'IL', 'name' => 'Israel', 'dial' => '+972'],
        ['iso' => 'TR', 'name' => 'Türkiye', 'dial' => '+90'],
        ['iso' => 'IN', 'name' => 'India', 'dial' => '+91'],
        ['iso' => 'PK', 'name' => 'Pakistan', 'dial' => '+92'],
        ['iso' => 'BD', 'name' => 'Bangladesh', 'dial' => '+880'],
        ['iso' => 'CN', 'name' => 'China', 'dial' => '+86'],
        ['iso' => 'JP', 'name' => 'Japan', 'dial' => '+81'],
        ['iso' => 'KR', 'name' => 'South Korea', 'dial' => '+82'],
        ['iso' => 'SG', 'name' => 'Singapore', 'dial' => '+65'],
        ['iso' => 'MY', 'name' => 'Malaysia', 'dial' => '+60'],
        ['iso' => 'ID', 'name' => 'Indonesia', 'dial' => '+62'],
        ['iso' => 'PH', 'name' => 'Philippines', 'dial' => '+63'],
        ['iso' => 'TH', 'name' => 'Thailand', 'dial' => '+66'],
        ['iso' => 'VN', 'name' => 'Vietnam', 'dial' => '+84'],
        ['iso' => 'AU', 'name' => 'Australia', 'dial' => '+61'],
        ['iso' => 'NZ', 'name' => 'New Zealand', 'dial' => '+64'],
        ['iso' => 'BR', 'name' => 'Brazil', 'dial' => '+55'],
        ['iso' => 'MX', 'name' => 'Mexico', 'dial' => '+52'],
        ['iso' => 'AR', 'name' => 'Argentina', 'dial' => '+54'],
        ['iso' => 'CL', 'name' => 'Chile', 'dial' => '+56'],
        ['iso' => 'CO', 'name' => 'Colombia', 'dial' => '+57'],
    ],
];
