/**
 * Pure, framework-free table logic for <DataTable>.
 *
 * Kept separate from the component so it can be unit-tested in isolation
 * (see logic.test.js). No React, no DOM, no side effects.
 */

/**
 * Compare two cell values for sorting. Numeric when both look numeric
 * (natural/locale-aware otherwise); null/undefined sort first.
 *
 * @param {*} a
 * @param {*} b
 * @return {number} negative / 0 / positive
 */
export function compareValues( a, b ) {
	if ( a === b ) {
		return 0;
	}
	const aEmpty = a === null || a === undefined || a === '';
	const bEmpty = b === null || b === undefined || b === '';
	if ( aEmpty && bEmpty ) {
		return 0;
	}
	if ( aEmpty ) {
		return -1;
	}
	if ( bEmpty ) {
		return 1;
	}

	const na = Number( a );
	const nb = Number( b );
	if ( ! Number.isNaN( na ) && ! Number.isNaN( nb ) ) {
		return na - nb;
	}

	return String( a ).localeCompare( String( b ), undefined, {
		numeric: true,
		sensitivity: 'base',
	} );
}

/**
 * Stable sort of rows by a key. Returns a new array; input is not mutated.
 *
 * @param {Array}    rows
 * @param {string}   sortKey
 * @param {string}   sortDir  'asc' | 'desc' | 'none'
 * @param {Function} getValue (row, key) => value
 * @return {Array}
 */
export function sortRows( rows, sortKey, sortDir, getValue = ( row, key ) => row[ key ] ) {
	if ( ! sortKey || sortDir === 'none' || ! sortDir ) {
		return rows.slice();
	}
	const dir = sortDir === 'desc' ? -1 : 1;
	return rows
		.map( ( row, index ) => [ row, index ] )
		.sort( ( [ a, ai ], [ b, bi ] ) => {
			const c = compareValues( getValue( a, sortKey ), getValue( b, sortKey ) );
			return c !== 0 ? c * dir : ai - bi; // stable tiebreak on original index
		} )
		.map( ( [ row ] ) => row );
}

/**
 * Case-insensitive substring filter across the given keys (all own values
 * when no keys are provided).
 *
 * @param {Array}         rows
 * @param {string}        query
 * @param {Array<string>} [searchKeys]
 * @return {Array}
 */
export function filterRows( rows, query, searchKeys ) {
	const q = String( query || '' ).trim().toLowerCase();
	if ( ! q ) {
		return rows.slice();
	}
	const keys = searchKeys && searchKeys.length ? searchKeys : null;
	return rows.filter( ( row ) => {
		const values = keys ? keys.map( ( k ) => row[ k ] ) : Object.values( row );
		return values.some(
			( v ) => v !== null && v !== undefined && String( v ).toLowerCase().includes( q )
		);
	} );
}

/**
 * Total pages for a row count and page size.
 *
 * @param {number} total
 * @param {number} pageSize
 * @return {number} at least 1
 */
export function pageCount( total, pageSize ) {
	if ( ! pageSize || pageSize <= 0 ) {
		return 1;
	}
	return Math.max( 1, Math.ceil( total / pageSize ) );
}

/**
 * Clamp a page number into the valid [1, pageCount] range.
 *
 * @param {number} page
 * @param {number} total
 * @param {number} pageSize
 * @return {number}
 */
export function clampPage( page, total, pageSize ) {
	const last = pageCount( total, pageSize );
	if ( ! page || page < 1 ) {
		return 1;
	}
	return page > last ? last : page;
}

/**
 * Slice one page of rows (1-indexed page). pageSize <= 0 returns all rows.
 *
 * @param {Array}  rows
 * @param {number} page
 * @param {number} pageSize
 * @return {Array}
 */
export function paginate( rows, page, pageSize ) {
	if ( ! pageSize || pageSize <= 0 ) {
		return rows.slice();
	}
	const p = clampPage( page, rows.length, pageSize );
	const start = ( p - 1 ) * pageSize;
	return rows.slice( start, start + pageSize );
}

/**
 * Split text into [{ text, match }] segments for highlight rendering.
 * Every occurrence of the query is flagged; matching is case-insensitive.
 *
 * @param {*}      text
 * @param {string} query
 * @return {Array<{text: string, match: boolean}>}
 */
export function highlightSegments( text, query ) {
	const s = String( text === null || text === undefined ? '' : text );
	const q = String( query || '' ).trim();
	if ( ! q ) {
		return [ { text: s, match: false } ];
	}
	const segments = [];
	const lower = s.toLowerCase();
	const lq = q.toLowerCase();
	let i = 0;
	while ( i <= s.length ) {
		const idx = lower.indexOf( lq, i );
		if ( idx === -1 ) {
			if ( i < s.length ) {
				segments.push( { text: s.slice( i ), match: false } );
			}
			break;
		}
		if ( idx > i ) {
			segments.push( { text: s.slice( i, idx ), match: false } );
		}
		segments.push( { text: s.slice( idx, idx + q.length ), match: true } );
		i = idx + q.length;
	}
	return segments.length ? segments : [ { text: s, match: false } ];
}

/**
 * Next sort direction cycling asc -> desc -> none for a column.
 *
 * @param {string} currentKey
 * @param {string} currentDir
 * @param {string} nextKey
 * @return {{sortKey: string|null, sortDir: string}}
 */
export function nextSort( currentKey, currentDir, nextKey ) {
	if ( currentKey !== nextKey ) {
		return { sortKey: nextKey, sortDir: 'asc' };
	}
	if ( currentDir === 'asc' ) {
		return { sortKey: nextKey, sortDir: 'desc' };
	}
	if ( currentDir === 'desc' ) {
		return { sortKey: null, sortDir: 'none' };
	}
	return { sortKey: nextKey, sortDir: 'asc' };
}
