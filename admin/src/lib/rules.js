/**
 * Parse/serialize the plugin's country + precision rule strings.
 *
 * Storage format (see IP_Location_Block::validate_list_match): a comma-separated
 * list where each entry is one of:
 *   CC              country only            e.g. "US"
 *   CC:State:Name   state-level (3 parts)   e.g. "US:State:California"
 *   CC:City:Name    city-level  (3 parts)   e.g. "US:City:Seattle"
 *   CC:Name         city shorthand (2 parts, treated as City by the engine)
 *
 * The precision key must be the literal "State" or "City" — the geolocation
 * result has no "region" field ("Region" in the UI copy maps to state data).
 */

/**
 * @param {string} str stored rule list
 * @return {{countries: string[], precise: Array<{country:string, level:'State'|'City', value:string}>}}
 */
export function parseRules( str ) {
	const countries = [];
	const precise = [];
	if ( ! str || typeof str !== 'string' ) {
		return { countries, precise };
	}
	str.split( ',' ).forEach( ( raw ) => {
		const part = raw.trim();
		if ( ! part ) {
			return;
		}
		const bits = part.split( ':' ).map( ( b ) => b.trim() );
		const cc = ( bits[ 0 ] || '' ).toUpperCase();
		if ( ! cc ) {
			return;
		}
		if ( bits.length >= 3 && bits[ 2 ] ) {
			const level = /^city$/i.test( bits[ 1 ] ) ? 'City' : 'State';
			precise.push( { country: cc, level, value: bits.slice( 2 ).join( ':' ) } );
		} else if ( bits.length === 2 && bits[ 1 ] ) {
			// Two-part shorthand is a city rule.
			precise.push( { country: cc, level: 'City', value: bits[ 1 ] } );
		} else {
			countries.push( cc );
		}
	} );
	return { countries, precise };
}

/**
 * @param {{countries?: string[], precise?: Array<{country:string, level:string, value:string}>}} data
 * @return {string}
 */
export function serializeRules( { countries = [], precise = [] } = {} ) {
	const parts = [];
	const seen = new Set();

	countries.forEach( ( cc ) => {
		const code = String( cc || '' ).toUpperCase().trim();
		if ( code && ! seen.has( code ) ) {
			seen.add( code );
			parts.push( code );
		}
	} );

	precise.forEach( ( r ) => {
		const code = String( r.country || '' ).toUpperCase().trim();
		const level = /^city$/i.test( r.level ) ? 'City' : 'State';
		const value = String( r.value || '' ).trim();
		if ( code && value ) {
			const key = `${ code }:${ level }:${ value }`;
			if ( ! seen.has( key ) ) {
				seen.add( key );
				parts.push( key );
			}
		}
	} );

	return parts.join( ',' );
}
