<?php

Error::deprecated('Language::getCountriesList()');

$countries = array();

if(class_exists('App') && App::getLang()) {

	switch(App::getLang()) {

		case 'de':

			$countries = array(
			'de' => 'Deutschland',
			'at' => 'Österreich',
			'ch' => 'Schweiz',
			'af' => 'Afghanistan',
			'eg' => 'Ägypten',
			'al' => 'Albanien',
			'dz' => 'Algerien',
			'ad' => 'Andorra',
			'ao' => 'Angola',
			'ar' => 'Argentinien',
			'am' => 'Armenien',
			'az' => 'Aserbaidschan',
			'et' => 'Äthiopien',
			'au' => 'Australien',
			'bs' => 'Bahamas',
			'bh' => 'Bahrein',
			'bd' => 'Bangladesch',
			'bb' => 'Barbados',
			'be' => 'Belgien',
			'bz' => 'Belize',
			'bj' => 'Benin',
			'bm' => 'Bermuda',
			'bt' => 'Bhutan',
			'bo' => 'Bolivien',
			'ba' => 'Bosnien und Herzegowina',
			'bw' => 'Botswana',
			'br' => 'Brasilien',
			'bn' => 'Brunei',
			'bg' => 'Bulgarien',
			'bf' => 'Burkina Faso',
			'bi' => 'Burundi',
			'cl' => 'Chile',
			'cn' => 'China',
			'cr' => 'Costa Rica',
			'dk' => 'Dänemark',
			'dm' => 'Dominica',
			'do' => 'Dominikanische Republik',
			'dj' => 'Dschibuti',
			'ec' => 'Ecuador',
			'sv' => 'El Salvador',
			'ci' => 'Elfenbeinküste',
			'er' => 'Eritrea',
			'ee' => 'Estland',
			'fk' => 'Falkland Inseln',
			'fj' => 'Fidschi',
			'fi' => 'Finnland',
			'fr' => 'Frankreich',
			'ga' => 'Gabun',
			'gm' => 'Gambia',
			'tg' => 'Gehen',
			'ge' => 'Georgia',
			'gh' => 'Ghana',
			'gi' => 'Gibraltar',
			'gr' => 'Griechenland',
			'gb' => 'Großbritannien',
			'gu' => 'Guam',
			'gt' => 'Guatemala',
			'gn' => 'Guinea',
			'gy' => 'Guyana',
			'ht' => 'Haiti',
			'hn' => 'Honduras',
			'hk' => 'Hongkong',
			'ir' => 'Ich rannte',
			'in' => 'Indien',
			'id' => 'Indonesien',
			'iq' => 'Irak',
			'ie' => 'Irland',
			'is' => 'Island',
			'il' => 'Israel',
			'it' => 'Italien',
			'jm' => 'Jamaika',
			'jp' => 'Japan',
			'ye' => 'Jemen',
			'jo' => 'Jordanien',
			'kh' => 'Kambodscha',
			'cm' => 'Kamerun',
			'ca' => 'Kanada',
			'kz' => 'Kasachstan',
			'qa' => 'Katar',
			'ke' => 'Kenia',
			'kg' => 'Kirgisistan',
			'ki' => 'Kiribati',
			'co' => 'Kolumbien',
			'cg' => 'Kongo',
			'cd' => 'Kongo',
			'hr' => 'Kroatien',
			'cu' => 'Kuba',
			'kw' => 'Kuwait',
			'la' => 'Laos',
			'ls' => 'Lesotho',
			'lv' => 'Lettland',
			'lb' => 'Libanon',
			'lr' => 'Liberia',
			'ly' => 'Libyen',
			'li' => 'Liechtenstein',
			'lt' => 'Litauen',
			'lu' => 'Luxemburg',
			'mo' => 'Macau',
			'mg' => 'Madagaskar',
			'mw' => 'Malawi',
			'my' => 'Malaysia',
			'mv' => 'Malediven',
			'ml' => 'Mali',
			'mt' => 'Malta',
			'ma' => 'Marokko',
			'mr' => 'Mauretanien',
			'mu' => 'Mauritius',
			'mk' => 'Mazedonien',
			'mx' => 'Mexiko',
			'md' => 'Moldawien',
			'mc' => 'Monaco',
			'mn' => 'Mongolei',
			'me' => 'Montenegro',
			'mz' => 'Mosambik',
			'mm' => 'Myanmar',
			'na' => 'Namibia',
			'nr' => 'Nauru',
			'np' => 'Nepal',
			'nz' => 'Neuseeland',
			'ni' => 'Nicaragua',
			'nl' => 'Niederlande',
			'ne' => 'Niger',
			'ng' => 'Nigeria',
			'kp' => 'Nord Korea',
			'no' => 'Norwegen',
			'om' => 'Oman',
			'tl' => 'Ost-Timor',
			'pk' => 'Pakistan',
			'ps' => 'Palästinensisches Gebiet',
			'pw' => 'Palau',
			'pa' => 'Panama',
			'pg' => 'Papua Neu-Guinea',
			'py' => 'Paraguay',
			'pe' => 'Peru',
			'ph' => 'Philippinen',
			'pl' => 'Polen',
			'pt' => 'Portugal',
			'pr' => 'Puerto Rico',
			'rw' => 'Ruanda',
			'ro' => 'Rumänien',
			'ru' => 'Russische Föderation',
			'zm' => 'Sambia',
			'ws' => 'Samoa',
			'sm' => 'San Marino',
			'sa' => 'Saudi Arabien',
			'se' => 'Schweden',
			'sn' => 'Senegal',
			'rs' => 'Serbien',
			'sc' => 'Seychellen',
			'sl' => 'Sierra Leone',
			'zw' => 'Simbabwe',
			'sg' => 'Singapur',
			'sk' => 'Slowakei',
			'si' => 'Slowenien',
			'so' => 'Somalia',
			'es' => 'Spanien',
			'lk' => 'Sri Lanka',
			'lc' => 'St. Lucia',
			'za' => 'Südafrika',
			'sd' => 'Sudan',
			'kr' => 'Südkorea',
			'sr' => 'Suriname',
			'sz' => 'Swasiland',
			'sy' => 'Syrien',
			'tj' => 'Tadschikistan',
			'tw' => 'Taiwan',
			'tz' => 'Tansania',
			'th' => 'Thailand',
			'tk' => 'Tokelau',
			'to' => 'Tonga',
			'tt' => 'Trinidad und Tobago',
			'tr' => 'Truthahn',
			'td' => 'Tschad',
			'cz' => 'Tschechien',
			'tn' => 'Tunesien',
			'tm' => 'Turkmenistan',
			'ug' => 'Uganda',
			'ua' => 'Ukraine',
			'hu' => 'Ungarn',
			'uy' => 'Uruguay',
			'uz' => 'Usbekistan',
			'va' => 'Vatikanstadt',
			've' => 'Venezuela',
			'ae' => 'Vereinigte Arabische Emirate',
			'us' => 'Vereinigte Staaten',
			'vn' => 'Vietnam',
			'by' => 'Weißrussland',
			'cf' => 'Zentralafrikanische Republik',
			'cy' => 'Zypern'
			);
			break;

		default:

			$countries = array(
			'af' => 'Afghanistan',
			'al' => 'Albania',
			'dz' => 'Algeria',
			'ad' => 'Andorra',
			'ao' => 'Angola',
			'ar' => 'Argentina',
			'am' => 'Armenia',
			'au' => 'Australia',
			'at' => 'Austria',
			'az' => 'Azerbaijan',
			'bs' => 'Bahamas',
			'bh' => 'Bahrain',
			'bd' => 'Bangladesh',
			'bb' => 'Barbados',
			'by' => 'Belarus',
			'be' => 'Belgium',
			'bz' => 'Belize',
			'bj' => 'Benin',
			'bm' => 'Bermuda',
			'bt' => 'Bhutan',
			'bo' => 'Bolivia',
			'ba' => 'Bosnia and Herzegovina',
			'bw' => 'Botswana',
			'br' => 'Brazil',
			'bn' => 'Brunei',
			'bg' => 'Bulgaria',
			'bf' => 'Burkina Faso',
			'bi' => 'Burundi',
			'kh' => 'Cambodia',
			'cm' => 'Cameroon',
			'ca' => 'Canada',
			'cf' => 'Central African Republic',
			'td' => 'Chad',
			'cl' => 'Chile',
			'cn' => 'China',
			'co' => 'Colombia',
			'cg' => 'Congo',
			'cd' => 'Congo',
			'cr' => 'Costa Rica',
			'hr' => 'Croatia',
			'cu' => 'Cuba',
			'cy' => 'Cyprus',
			'cz' => 'Czech Republic',
			'dk' => 'Denmark',
			'dj' => 'Djibouti',
			'dm' => 'Dominica',
			'do' => 'Dominican Republic',
			'tl' => 'East Timor',
			'ec' => 'Ecuador',
			'eg' => 'Egypt',
			'sv' => 'El Salvador',
			'er' => 'Eritrea',
			'ee' => 'Estonia',
			'et' => 'Ethiopia',
			'fk' => 'Falkland Islands',
			'fj' => 'Fiji',
			'fi' => 'Finland',
			'fr' => 'France',
			'ga' => 'Gabon',
			'gm' => 'Gambia',
			'ge' => 'Georgia',
			'de' => 'Germany',
			'gh' => 'Ghana',
			'gi' => 'Gibraltar',
			'gr' => 'Greece',
			'gu' => 'Guam',
			'gt' => 'Guatemala',
			'gn' => 'Guinea',
			'gy' => 'Guyana',
			'ht' => 'Haiti',
			'hn' => 'Honduras',
			'hk' => 'Hong Kong',
			'hu' => 'Hungary',
			'is' => 'Iceland',
			'in' => 'India',
			'id' => 'Indonesia',
			'ir' => 'Iran',
			'iq' => 'Iraq',
			'ie' => 'Ireland',
			'il' => 'Israel',
			'it' => 'Italy',
			'ci' => 'Ivory Coast',
			'jm' => 'Jamaica',
			'jp' => 'Japan',
			'jo' => 'Jordan',
			'kz' => 'Kazakhstan',
			'ke' => 'Kenya',
			'ki' => 'Kiribati',
			'kw' => 'Kuwait',
			'kg' => 'Kyrgyzstan',
			'la' => 'Laos',
			'lv' => 'Latvia',
			'lb' => 'Lebanon',
			'ls' => 'Lesotho',
			'lr' => 'Liberia',
			'ly' => 'Libya',
			'li' => 'Liechtenstein',
			'lt' => 'Lithuania',
			'lu' => 'Luxembourg',
			'mo' => 'Macao',
			'mk' => 'Macedonia',
			'mg' => 'Madagascar',
			'mw' => 'Malawi',
			'my' => 'Malaysia',
			'mv' => 'Maldives',
			'ml' => 'Mali',
			'mt' => 'Malta',
			'mr' => 'Mauritania',
			'mu' => 'Mauritius',
			'mx' => 'Mexico',
			'md' => 'Moldova',
			'mc' => 'Monaco',
			'mn' => 'Mongolia',
			'me' => 'Montenegro',
			'ma' => 'Morocco',
			'mz' => 'Mozambique',
			'mm' => 'Myanmar',
			'na' => 'Namibia',
			'nr' => 'Nauru',
			'np' => 'Nepal',
			'nl' => 'Netherlands',
			'nz' => 'New Zealand',
			'ni' => 'Nicaragua',
			'ne' => 'Niger',
			'ng' => 'Nigeria',
			'kp' => 'North Korea',
			'no' => 'Norway',
			'om' => 'Oman',
			'pk' => 'Pakistan',
			'pw' => 'Palau',
			'ps' => 'Palestinian Territory',
			'pa' => 'Panama',
			'pg' => 'Papua New Guinea',
			'py' => 'Paraguay',
			'pe' => 'Peru',
			'ph' => 'Philippines',
			'pl' => 'Poland',
			'pt' => 'Portugal',
			'pr' => 'Puerto Rico',
			'qa' => 'Qatar',
			'ro' => 'Romania',
			'ru' => 'Russian Federation',
			'rw' => 'Rwanda',
			'lc' => 'Saint Lucia',
			'ws' => 'Samoa',
			'sm' => 'San Marino',
			'sa' => 'Saudi Arabia',
			'sn' => 'Senegal',
			'rs' => 'Serbia',
			'sc' => 'Seychelles',
			'sl' => 'Sierra Leone',
			'sg' => 'Singapore',
			'sk' => 'Slovakia',
			'si' => 'Slovenia',
			'so' => 'Somalia',
			'za' => 'South Africa',
			'kr' => 'South Korea',
			'es' => 'Spain',
			'lk' => 'Sri Lanka',
			'sd' => 'Sudan',
			'sr' => 'Suriname',
			'sz' => 'Swaziland',
			'se' => 'Sweden',
			'ch' => 'Switzerland',
			'sy' => 'Syria',
			'tw' => 'Taiwan',
			'tj' => 'Tajikistan',
			'tz' => 'Tanzania',
			'th' => 'Thailand',
			'tg' => 'Togo',
			'tk' => 'Tokelau',
			'to' => 'Tonga',
			'tt' => 'Trinidad and Tobago',
			'tn' => 'Tunisia',
			'tr' => 'Turkey',
			'tm' => 'Turkmenistan',
			'ug' => 'Uganda',
			'ua' => 'Ukraine',
			'ae' => 'United Arab Emirates',
			'gb' => 'United Kingdom',
			'us' => 'United States',
			'uy' => 'Uruguay',
			'uz' => 'Uzbekistan',
			'va' => 'Vatican City',
			've' => 'Venezuela',
			'vn' => 'Vietnam',
			'ye' => 'Yemen',
			'zm' => 'Zambia',
			'zw' => 'Zimbabwe'
			);

	}

}elseif(class_exists('Error')) {

	Error::warning('Country list could not be loaded.');

}
