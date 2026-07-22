/**
 * Region (state / province / district) name lists for the five countries whose
 * precise-rule "State / Region" field is offered as a searchable dropdown.
 *
 * The values are the EXACT `regionName` strings IP2Location returns. The matcher
 * (IP_Location_Block::validate_list_match) compares case-insensitively but
 * otherwise character-for-character, so these must match verbatim. Sourced from
 * IP2Location's own IP2LOCATION-ISO3166-2 subdivision dataset and verified
 * against live api.iplocationblock.com samples during implementation — every
 * distinct sample matched byte-for-byte:
 *
 *   US  California, Washington              (+ in-repo fixtures)
 *   AU  New South Wales, Victoria
 *   JP  Tokyo, Osaka, Kyoto                 (ASCII romanization, no macrons)
 *   IL  Tel Aviv, Yerushalayim, Hefa        (ISO 3166-2 romanization)
 *   ES  "Madrid, Comunidad de", Catalunya,  (autonomous communities: local
 *       Andalucia, Extremadura              variants, diacritics stripped, the
 *                                           ISO comma-inversion preserved)
 *
 * All names are pure ASCII. ES is at autonomous-community granularity (19 incl.
 * Ceuta / Melilla) — that is the level the provider returns for Spain.
 */

export const REGIONS = {
	US: [
		'Alabama',
		'Alaska',
		'Arizona',
		'Arkansas',
		'California',
		'Colorado',
		'Connecticut',
		'Delaware',
		'District of Columbia',
		'Florida',
		'Georgia',
		'Hawaii',
		'Idaho',
		'Illinois',
		'Indiana',
		'Iowa',
		'Kansas',
		'Kentucky',
		'Louisiana',
		'Maine',
		'Maryland',
		'Massachusetts',
		'Michigan',
		'Minnesota',
		'Mississippi',
		'Missouri',
		'Montana',
		'Nebraska',
		'Nevada',
		'New Hampshire',
		'New Jersey',
		'New Mexico',
		'New York',
		'North Carolina',
		'North Dakota',
		'Ohio',
		'Oklahoma',
		'Oregon',
		'Pennsylvania',
		'Rhode Island',
		'South Carolina',
		'South Dakota',
		'Tennessee',
		'Texas',
		'Utah',
		'Vermont',
		'Virginia',
		'Washington',
		'West Virginia',
		'Wisconsin',
		'Wyoming',
	],
	ES: [
		'Andalucia',
		'Aragon',
		'Asturias, Principado de',
		'Canarias',
		'Cantabria',
		'Castilla y Leon',
		'Castilla-La Mancha',
		'Catalunya',
		'Ceuta',
		'Extremadura',
		'Galicia',
		'Illes Balears',
		'La Rioja',
		'Madrid, Comunidad de',
		'Melilla',
		'Murcia, Region de',
		'Navarra, Comunidad Foral de',
		'Pais Vasco',
		'Valenciana, Comunidad',
	],
	AU: [
		'Australian Capital Territory',
		'New South Wales',
		'Northern Territory',
		'Queensland',
		'South Australia',
		'Tasmania',
		'Victoria',
		'Western Australia',
	],
	JP: [
		'Aichi',
		'Akita',
		'Aomori',
		'Chiba',
		'Ehime',
		'Fukui',
		'Fukuoka',
		'Fukushima',
		'Gifu',
		'Gunma',
		'Hiroshima',
		'Hokkaido',
		'Hyogo',
		'Ibaraki',
		'Ishikawa',
		'Iwate',
		'Kagawa',
		'Kagoshima',
		'Kanagawa',
		'Kochi',
		'Kumamoto',
		'Kyoto',
		'Mie',
		'Miyagi',
		'Miyazaki',
		'Nagano',
		'Nagasaki',
		'Nara',
		'Niigata',
		'Oita',
		'Okayama',
		'Okinawa',
		'Osaka',
		'Saga',
		'Saitama',
		'Shiga',
		'Shimane',
		'Shizuoka',
		'Tochigi',
		'Tokushima',
		'Tokyo',
		'Tottori',
		'Toyama',
		'Wakayama',
		'Yamagata',
		'Yamaguchi',
		'Yamanashi',
	],
	IL: [
		'HaDarom',
		'HaMerkaz',
		'HaTsafon',
		'Hefa',
		'Tel Aviv',
		'Yerushalayim',
	],
};

/**
 * Whether a country code has a bundled region list (case-insensitive).
 *
 * @param {string} code alpha-2 code
 * @return {boolean} true when a bundled list exists for the code
 */
export function hasRegionList( code ) {
	return Array.isArray( REGIONS[ String( code || '' ).toUpperCase() ] );
}

/**
 * The bundled region list for a country, or an empty array.
 *
 * @param {string} code alpha-2 code
 * @return {string[]} the region names, or an empty array
 */
export function regionList( code ) {
	return REGIONS[ String( code || '' ).toUpperCase() ] || [];
}
