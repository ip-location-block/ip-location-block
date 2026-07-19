/**
 * Parse / serialize the `public.ua_list` bot-rule string.
 *
 * Grammar (see IP_Location_Block::check_ua and Util::multiexplode):
 *   - A flat list; `,` and `\n` are equivalent separators, empties dropped.
 *   - Each entry is  UA<sep>QUAL  where <sep> is `:` (ALLOW / pass) or `#`
 *     (BLOCK). The engine treats an entry as a BLOCK when it contains a `#`
 *     anywhere; otherwise it is an ALLOW.
 *   - UA is matched as a plain, case-SENSITIVE substring of the request's
 *     User-Agent header (or `*` for "any UA"). Its case is preserved verbatim.
 *   - A leading `!` on QUAL negates the qualification.
 *   - QUAL is one of: `*` (any), a 2-letter country code, `HOST` /
 *     `HOST=str` (verified reverse DNS), `FEED`, `AS<n>` (ASN), `REF=str`
 *     (referer contains), or an IP / CIDR.
 *
 * The qualification keyword is stored canonically UPPER-CASE (`HOST`, `FEED`,
 * `AS…`, `REF=`, country codes) to match what the classic sanitizer emits and
 * to keep the builder's round-trip stable; the UA substring and the values of
 * `HOST=` / `REF=` keep their original case.
 *
 * Row shape: { ua, action:'pass'|'block', qualType, qualValue, negate }
 *   qualType ∈ 'any' | 'country' | 'host' | 'feed' | 'ip' | 'asn' | 'ref' | 'other'
 *   ('other' preserves an unrecognized qualifier verbatim for full back-compat;
 *    the engine ignores such entries — they are effectively no-ops.)
 */

/** qualTypes whose value is mandatory — an empty value makes the row degenerate. */
const VALUE_REQUIRED = [ 'country', 'ip', 'asn', 'ref' ];

/**
 * Classify a qualifier string (already stripped of a leading `!`).
 *
 * @param {string} code
 * @return {{qualType:string, qualValue:string}}
 */
function classifyQual( code ) {
	const raw = String( code || '' );
	if ( raw === '' ) {
		return { qualType: 'other', qualValue: '' };
	}
	if ( raw === '*' ) {
		return { qualType: 'any', qualValue: '' };
	}
	const upper = raw.toUpperCase();
	if ( upper === 'FEED' ) {
		return { qualType: 'feed', qualValue: '' };
	}
	if ( upper === 'HOST' ) {
		return { qualType: 'host', qualValue: '' };
	}
	if ( upper.startsWith( 'HOST=' ) ) {
		return { qualType: 'host', qualValue: raw.slice( 5 ) };
	}
	if ( upper.startsWith( 'REF=' ) ) {
		return { qualType: 'ref', qualValue: raw.slice( 4 ) };
	}
	if ( /^AS\d+$/i.test( raw ) ) {
		return { qualType: 'asn', qualValue: 'AS' + raw.replace( /^AS/i, '' ) };
	}
	if ( /^[A-Za-z]{2}$/.test( raw ) ) {
		return { qualType: 'country', qualValue: upper };
	}
	if ( /^[a-fA-F\d.:/]+$/.test( raw ) ) {
		return { qualType: 'ip', qualValue: raw };
	}
	return { qualType: 'other', qualValue: raw };
}

/**
 * Serialize one row into its `UA<sep>QUAL` token, or '' when the row cannot
 * produce a meaningful entry (no UA, or a value-required qualifier left blank).
 *
 * @param {object} row
 * @return {string}
 */
export function serializeRow( row ) {
	const ua = String( row?.ua ?? '' ).trim();
	if ( ! ua ) {
		return '';
	}
	const sep = row.action === 'block' ? '#' : ':';
	const neg = row.negate ? '!' : '';
	const value = String( row.qualValue ?? '' ).trim();

	if ( VALUE_REQUIRED.includes( row.qualType ) && ! value ) {
		return ''; // incomplete row — drop it (mirrors the precise-rule editor)
	}

	let qual;
	switch ( row.qualType ) {
		case 'any':
			qual = '*';
			break;
		case 'country':
			qual = value.toUpperCase();
			break;
		case 'host':
			qual = value ? `HOST=${ value }` : 'HOST';
			break;
		case 'feed':
			qual = 'FEED';
			break;
		case 'asn':
			qual = 'AS' + value.replace( /^AS/i, '' );
			break;
		case 'ref':
			qual = `REF=${ value }`;
			break;
		case 'ip':
			qual = value;
			break;
		case 'other':
		default:
			// A bare / unrecognized entry with no value carried nothing the
			// engine acts on; drop it rather than emit a trailing separator.
			if ( ! value ) {
				return '';
			}
			qual = value;
			break;
	}

	return `${ ua }${ sep }${ neg }${ qual }`;
}

/**
 * A canonical, case-normalized key for a row, used to dedupe rows and to detect
 * whether a preset's rules are already present.
 *
 * @param {object} row
 * @return {string}
 */
export function ruleKey( row ) {
	return serializeRow( row );
}

/**
 * Parse a stored `ua_list` string into editor rows.
 *
 * @param {string} str
 * @return {Array<object>} rows
 */
export function parseUaList( str ) {
	if ( ! str || typeof str !== 'string' ) {
		return [];
	}
	const rows = [];
	str.split( /[,\n]/ ).forEach( ( raw ) => {
		const token = raw.trim();
		if ( ! token ) {
			return;
		}
		const action = token.includes( '#' ) ? 'block' : 'pass';
		// Split on the FIRST separator (`:` or `#`).
		const sepIndex = token.search( /[:#]/ );
		let ua;
		let code;
		if ( sepIndex === -1 ) {
			ua = token;
			code = '';
		} else {
			ua = token.slice( 0, sepIndex );
			code = token.slice( sepIndex + 1 );
		}
		let negate = false;
		if ( code.startsWith( '!' ) ) {
			negate = true;
			code = code.slice( 1 );
		}
		const { qualType, qualValue } = classifyQual( code );
		rows.push( { ua, action, qualType, qualValue, negate } );
	} );
	return rows;
}

/**
 * Serialize editor rows back into a stored `ua_list` string. Rows that
 * serialize to '' (blank / incomplete) are dropped.
 *
 * @param {Array<object>} rows
 * @return {string}
 */
export function serializeUaList( rows ) {
	return ( rows || [] )
		.map( serializeRow )
		.filter( Boolean )
		.join( ',' );
}

/**
 * Normalize a stored list to a canonical, whitespace-insensitive token string
 * (newlines treated as commas). Used to recognize known legacy defaults.
 *
 * @param {string} str
 * @return {string}
 */
export function normalizeUaList( str ) {
	if ( ! str || typeof str !== 'string' ) {
		return '';
	}
	return str
		.split( /[,\n]/ )
		.map( ( token ) => token.trim() )
		.filter( Boolean )
		.join( ',' );
}
