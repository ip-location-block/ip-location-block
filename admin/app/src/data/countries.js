/**
 * Country data for the Simple blocking UI.
 *
 * Only ISO 3166-1 alpha-2 codes are listed here — display names are resolved at
 * runtime via Intl.DisplayNames rather than bundling a name table. That keeps
 * the bundle small and makes the labels follow the admin's locale for free.
 * Flags are derived from the code (regional-indicator code points), so there
 * are no image assets either.
 */

// Pseudo-codes the plugin understands in addition to real countries
// (see the whitelist/blacklist help text in settingsSchema.js).
export const SPECIAL = {
	XX: 'Private / local network',
	YY: 'Non-country',
	ZZ: 'Unknown',
};

const CODES = (
	'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ ' +
	'BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR ' +
	'CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR ' +
	'GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ' +
	'ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ ' +
	'LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ ' +
	'MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF ' +
	'PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI ' +
	'SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR ' +
	'TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'
).split( ' ' );

let display = null;
try {
	if ( typeof Intl !== 'undefined' && Intl.DisplayNames ) {
		const locale =
			( typeof document !== 'undefined' && document.documentElement.lang ) || 'en';
		display = new Intl.DisplayNames( [ locale ], { type: 'region', fallback: 'code' } );
	}
} catch ( e ) {
	display = null; // unsupported locale/runtime — fall back to bare codes
}

/**
 * Localized country name, or the code itself when unresolvable.
 *
 * @param {string} code alpha-2 code
 * @return {string}
 */
export function countryName( code ) {
	const cc = String( code || '' ).toUpperCase();
	if ( SPECIAL[ cc ] ) {
		return SPECIAL[ cc ];
	}
	if ( display ) {
		try {
			const name = display.of( cc );
			if ( name && name !== cc ) {
				return name;
			}
		} catch ( e ) {
			// ignore — fall through to the code
		}
	}
	return cc;
}

/**
 * Flag emoji for a real country code ('' for pseudo-codes).
 *
 * @param {string} code alpha-2 code
 * @return {string}
 */
export function flagEmoji( code ) {
	const cc = String( code || '' ).toUpperCase();
	if ( ! /^[A-Z]{2}$/.test( cc ) || SPECIAL[ cc ] ) {
		return '';
	}
	return String.fromCodePoint(
		...[ ...cc ].map( ( c ) => 0x1f1e6 + c.charCodeAt( 0 ) - 65 )
	);
}

/**
 * Token label shown in the picker, e.g. "🇺🇸 United States (US)".
 *
 * @param {string} code alpha-2 code
 * @return {string}
 */
export function countryLabel( code ) {
	const cc = String( code || '' ).toUpperCase();
	const flag = flagEmoji( cc );
	return `${ flag ? flag + ' ' : '' }${ countryName( cc ) } (${ cc })`;
}

export const ALL_CODES = [ ...CODES, ...Object.keys( SPECIAL ) ];

/** Labels for FormTokenField suggestions. */
export const SUGGESTIONS = ALL_CODES.map( countryLabel );

/**
 * Map a token back to its code. Accepts a label produced by countryLabel(), a
 * bare code, or a typed country name.
 *
 * @param {string} token
 * @return {string} alpha-2 code, or '' when unrecognized
 */
export function codeFromToken( token ) {
	const raw = String( token || '' ).trim();
	if ( ! raw ) {
		return '';
	}
	// "… (US)" — the label form.
	const paren = raw.match( /\(([A-Za-z]{2})\)\s*$/ );
	if ( paren ) {
		return paren[ 1 ].toUpperCase();
	}
	// Bare code.
	if ( /^[A-Za-z]{2}$/.test( raw ) ) {
		return raw.toUpperCase();
	}
	// Typed name — resolve case-insensitively against known codes.
	const hit = ALL_CODES.find(
		( cc ) => countryName( cc ).toLowerCase() === raw.toLowerCase()
	);
	return hit || '';
}
