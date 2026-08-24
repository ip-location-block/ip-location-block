export const LOG_SEARCH_KEYS = [
	'ip',
	'code',
	'city',
	'state',
	'asn',
	'result',
	'target',
	'method',
	'userAgent',
	'headers',
	'postData',
];

export const LOG_PRESETS = [
	{ id: 'passed-whitelist', verdict: 'passed', listContext: 'whitelist' },
	{ id: 'passed-blacklist', verdict: 'passed', listContext: 'blacklist' },
	{ id: 'passed-none', verdict: 'passed', listContext: 'none' },
	{ id: 'blocked-whitelist', verdict: 'blocked', listContext: 'whitelist' },
	{ id: 'blocked-blacklist', verdict: 'blocked', listContext: 'blacklist' },
	{ id: 'blocked-none', verdict: 'blocked', listContext: 'none' },
];

export function filterLogRows( rows, query = '', preset = '' ) {
	const selected = LOG_PRESETS.find( ( item ) => item.id === preset );
	const q = String( query ).trim().toLowerCase();

	return rows.filter( ( row ) => {
		if (
			selected &&
			( row.verdict !== selected.verdict ||
				row.listContext !== selected.listContext )
		) {
			return false;
		}
		return (
			! q ||
			LOG_SEARCH_KEYS.some( ( key ) =>
				String( row[ key ] ?? '' )
					.toLowerCase()
					.includes( q )
			)
		);
	} );
}

const csvCell = ( value ) =>
	`"${ String( value ?? '' ).replace( /"/g, '""' ) }"`;

export function buildLogsCsv( rows ) {
	const fields = [
		[ 'Time', ( row ) => row.timeText || row.time ],
		[ 'IP address', ( row ) => row.ip ],
		[ 'Code', ( row ) => row.code ],
		[ 'City', ( row ) => row.city ],
		[ 'State', ( row ) => row.state ],
		[ 'ASN', ( row ) => row.asn ],
		[ 'Target', ( row ) => row.target ],
		[ 'Result', ( row ) => row.result ],
		[ 'Request', ( row ) => row.method ],
		[ 'User agent', ( row ) => row.userAgent ],
		[ 'HTTP headers', ( row ) => row.headers ],
		[ '$_POST data', ( row ) => row.postData ],
	];

	return [
		fields.map( ( [ label ] ) => csvCell( label ) ).join( ',' ),
		...rows.map( ( row ) =>
			fields.map( ( [ , get ] ) => csvCell( get( row ) ) ).join( ',' )
		),
	].join( '\r\n' );
}
