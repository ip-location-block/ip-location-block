/**
 * IP range <-> CIDR conversion for IPv4 and IPv6, dependency-free (BigInt).
 *
 * Ports the intent of the classic cidr.min.js helper as small, testable pure
 * functions:
 *   cidrToRange('192.168.0.0/16') -> { version, start, end }
 *   rangeToCidr('192.168.0.0', '192.168.255.255') -> ['192.168.0.0/16']
 * rangeToCidr returns the minimal list of aligned CIDR blocks covering the
 * (inclusive) range.
 */

const V4_BITS = 32n;
const V6_BITS = 128n;
const V4_MAX = ( 1n << V4_BITS ) - 1n;
const V6_MAX = ( 1n << V6_BITS ) - 1n;

const isV4 = ( ip ) => ip.indexOf( '.' ) !== -1 && ip.indexOf( ':' ) === -1;

/** Parse an IPv4 string to BigInt, or null. */
function v4ToInt( ip ) {
	const parts = ip.trim().split( '.' );
	if ( parts.length !== 4 ) {
		return null;
	}
	let out = 0n;
	for ( const part of parts ) {
		if ( ! /^\d{1,3}$/.test( part ) ) {
			return null;
		}
		const n = Number( part );
		if ( n > 255 ) {
			return null;
		}
		out = ( out << 8n ) + BigInt( n );
	}
	return out;
}

function v4ToStr( int ) {
	const o = [];
	for ( let i = 3n; i >= 0n; i-- ) {
		o.push( ( ( int >> ( i * 8n ) ) & 255n ).toString() );
	}
	return o.join( '.' );
}

/** Parse an IPv6 string (incl. :: and embedded IPv4) to BigInt, or null. */
function v6ToInt( ip ) {
	let str = ip.trim();
	if ( str.indexOf( ':' ) === -1 ) {
		return null;
	}

	// Embedded IPv4 tail (e.g. ::ffff:192.168.0.1)
	const dot = str.lastIndexOf( ':' );
	if ( str.indexOf( '.' ) !== -1 ) {
		const tail = str.slice( dot + 1 );
		const v4 = v4ToInt( tail );
		if ( v4 === null ) {
			return null;
		}
		str = str.slice( 0, dot + 1 ) +
			( ( v4 >> 16n ) & 0xffffn ).toString( 16 ) + ':' + ( v4 & 0xffffn ).toString( 16 );
	}

	const halves = str.split( '::' );
	if ( halves.length > 2 ) {
		return null;
	}
	const head = halves[ 0 ] ? halves[ 0 ].split( ':' ) : [];
	const tail = halves.length === 2 && halves[ 1 ] ? halves[ 1 ].split( ':' ) : [];

	let groups;
	if ( halves.length === 2 ) {
		const fill = 8 - head.length - tail.length;
		if ( fill < 0 ) {
			return null;
		}
		groups = [ ...head, ...Array( fill ).fill( '0' ), ...tail ];
	} else {
		groups = head;
	}
	if ( groups.length !== 8 ) {
		return null;
	}

	let out = 0n;
	for ( const g of groups ) {
		if ( ! /^[0-9a-fA-F]{1,4}$/.test( g ) ) {
			return null;
		}
		out = ( out << 16n ) + BigInt( parseInt( g, 16 ) );
	}
	return out;
}

function v6ToStr( int ) {
	const groups = [];
	for ( let i = 7n; i >= 0n; i-- ) {
		groups.push( Number( ( int >> ( i * 16n ) ) & 0xffffn ).toString( 16 ) );
	}
	// Collapse the longest run of zero groups to "::".
	let best = { start: -1, len: 0 };
	let cur = { start: -1, len: 0 };
	groups.forEach( ( g, i ) => {
		if ( g === '0' ) {
			if ( cur.start === -1 ) {
				cur = { start: i, len: 1 };
			} else {
				cur.len++;
			}
			if ( cur.len > best.len ) {
				best = { ...cur };
			}
		} else {
			cur = { start: -1, len: 0 };
		}
	} );
	if ( best.len > 1 ) {
		const before = groups.slice( 0, best.start ).join( ':' );
		const after = groups.slice( best.start + best.len ).join( ':' );
		return `${ before }::${ after }`;
	}
	return groups.join( ':' );
}

/** @return {{version:4|6, int:BigInt}|null} */
function parseIp( ip ) {
	const s = String( ip || '' ).trim();
	if ( ! s ) {
		return null;
	}
	if ( isV4( s ) ) {
		const int = v4ToInt( s );
		return int === null ? null : { version: 4, int };
	}
	const int = v6ToInt( s );
	return int === null ? null : { version: 6, int };
}

export function isValidIp( ip ) {
	return parseIp( ip ) !== null;
}

const toStr = ( version, int ) => ( version === 4 ? v4ToStr( int ) : v6ToStr( int ) );

/**
 * @param {string} cidr e.g. "192.168.0.0/16" or "2001:db8::/32"
 * @return {{version:4|6, start:string, end:string, prefix:number}|null}
 */
export function cidrToRange( cidr ) {
	const m = String( cidr || '' ).trim().match( /^(.+)\/(\d{1,3})$/ );
	if ( ! m ) {
		return null;
	}
	const parsed = parseIp( m[ 1 ] );
	if ( ! parsed ) {
		return null;
	}
	const bits = parsed.version === 4 ? V4_BITS : V6_BITS;
	const prefix = BigInt( m[ 2 ] );
	if ( prefix > bits ) {
		return null;
	}
	const hostBits = bits - prefix;
	const mask = hostBits === 0n ? 0n : ( 1n << hostBits ) - 1n;
	const start = parsed.int & ~mask & ( parsed.version === 4 ? V4_MAX : V6_MAX );
	const end = start | mask;
	return {
		version: parsed.version,
		prefix: Number( prefix ),
		start: toStr( parsed.version, start ),
		end: toStr( parsed.version, end ),
	};
}

/**
 * Minimal list of CIDR blocks covering the inclusive range [start, end].
 * @return {string[]|null}
 */
export function rangeToCidr( startIp, endIp ) {
	const a = parseIp( startIp );
	const b = parseIp( endIp );
	if ( ! a || ! b || a.version !== b.version ) {
		return null;
	}
	let start = a.int;
	const end = b.int;
	if ( start > end ) {
		return null;
	}
	const bits = a.version === 4 ? V4_BITS : V6_BITS;
	const out = [];

	while ( start <= end ) {
		// Largest aligned block starting at `start`: limited by alignment...
		let maxByAlign = 0n;
		while (
			maxByAlign < bits &&
			( start & ( ( 1n << ( maxByAlign + 1n ) ) - 1n ) ) === 0n
		) {
			maxByAlign++;
		}
		// ...and by how much of the range remains.
		let maxBySize = 0n;
		while ( maxBySize < bits && start + ( ( 1n << ( maxBySize + 1n ) ) - 1n ) <= end ) {
			maxBySize++;
		}
		const hostBits = maxByAlign < maxBySize ? maxByAlign : maxBySize;
		const prefix = bits - hostBits;
		out.push( `${ toStr( a.version, start ) }/${ Number( prefix ) }` );
		start += 1n << hostBits;
		// Guard against BigInt overflow past the max address.
		if ( hostBits === bits ) {
			break;
		}
	}

	return out;
}
