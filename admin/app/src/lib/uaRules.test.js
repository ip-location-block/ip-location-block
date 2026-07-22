import {
	parseUaList,
	serializeUaList,
	serializeRow,
	ruleKey,
	normalizeUaList,
} from './uaRules';

const row = ( ua, action, qualType, qualValue = '', negate = false ) => ( {
	ua,
	action,
	qualType,
	qualValue,
	negate,
} );

describe( 'parseUaList', () => {
	it( 'returns [] for falsy / non-string input', () => {
		expect( parseUaList( '' ) ).toEqual( [] );
		expect( parseUaList( null ) ).toEqual( [] );
		expect( parseUaList( undefined ) ).toEqual( [] );
	} );

	it( 'treats "," and "\\n" as equivalent separators and drops blanks', () => {
		expect( parseUaList( 'GPTBot#*,\nGooglebot:HOST,,' ) ).toEqual( [
			row( 'GPTBot', 'block', 'any' ),
			row( 'Googlebot', 'pass', 'host' ),
		] );
	} );

	it( 'reads `:` as ALLOW/pass and `#` as BLOCK', () => {
		expect( parseUaList( 'a:*' )[ 0 ].action ).toBe( 'pass' );
		expect( parseUaList( 'a#*' )[ 0 ].action ).toBe( 'block' );
	} );

	it( 'preserves UA-side case', () => {
		expect( parseUaList( 'Googlebot:HOST' )[ 0 ].ua ).toBe( 'Googlebot' );
		expect( parseUaList( 'facebookexternalhit:*' )[ 0 ].ua ).toBe(
			'facebookexternalhit'
		);
	} );

	it( 'classifies each qualification form', () => {
		expect( parseUaList( 'x:*' )[ 0 ] ).toMatchObject( { qualType: 'any' } );
		expect( parseUaList( 'x:US' )[ 0 ] ).toMatchObject( {
			qualType: 'country',
			qualValue: 'US',
		} );
		expect( parseUaList( 'x:HOST' )[ 0 ] ).toMatchObject( {
			qualType: 'host',
			qualValue: '',
		} );
		expect( parseUaList( 'x:HOST=embed.ly' )[ 0 ] ).toMatchObject( {
			qualType: 'host',
			qualValue: 'embed.ly',
		} );
		expect( parseUaList( '*:FEED' )[ 0 ] ).toMatchObject( {
			qualType: 'feed',
		} );
		expect( parseUaList( 'x:AS15169' )[ 0 ] ).toMatchObject( {
			qualType: 'asn',
			qualValue: 'AS15169',
		} );
		expect( parseUaList( 'x:REF=example.com' )[ 0 ] ).toMatchObject( {
			qualType: 'ref',
			qualValue: 'example.com',
		} );
		expect( parseUaList( 'x:192.168.0.0/16' )[ 0 ] ).toMatchObject( {
			qualType: 'ip',
			qualValue: '192.168.0.0/16',
		} );
	} );

	it( 'lower-cases the country code on the way in but normalizes to upper', () => {
		expect( parseUaList( 'x:us' )[ 0 ].qualValue ).toBe( 'US' );
	} );

	it( 'reads a leading "!" as negation', () => {
		expect( parseUaList( 'GPTBot#!US' )[ 0 ] ).toEqual(
			row( 'GPTBot', 'block', 'country', 'US', true )
		);
	} );

	it( 'keeps an IPv6 CIDR (split on the first separator only)', () => {
		expect( parseUaList( '*#2001:db8::/32' )[ 0 ] ).toEqual(
			row( '*', 'block', 'ip', '2001:db8::/32' )
		);
	} );

	it( 'preserves an unrecognized qualifier verbatim as "other"', () => {
		expect( parseUaList( 'x:weird_thing' )[ 0 ] ).toMatchObject( {
			qualType: 'other',
			qualValue: 'weird_thing',
		} );
	} );
} );

describe( 'serializeRow', () => {
	it( 'joins UA + separator + qualification', () => {
		expect( serializeRow( row( 'Googlebot', 'pass', 'host' ) ) ).toBe(
			'Googlebot:HOST'
		);
		expect( serializeRow( row( 'GPTBot', 'block', 'any' ) ) ).toBe(
			'GPTBot#*'
		);
		expect( serializeRow( row( '*', 'pass', 'feed' ) ) ).toBe( '*:FEED' );
		expect(
			serializeRow( row( 'Twitterbot', 'pass', 'country', 'us' ) )
		).toBe( 'Twitterbot:US' );
		expect(
			serializeRow( row( 'x', 'pass', 'host', 'embed.ly' ) )
		).toBe( 'x:HOST=embed.ly' );
		expect( serializeRow( row( 'x', 'pass', 'asn', '15169' ) ) ).toBe(
			'x:AS15169'
		);
	} );

	it( 'writes negation right after the separator', () => {
		expect(
			serializeRow( row( 'GPTBot', 'block', 'country', 'US', true ) )
		).toBe( 'GPTBot#!US' );
	} );

	it( 'drops a row with no UA', () => {
		expect( serializeRow( row( '', 'block', 'any' ) ) ).toBe( '' );
		expect( serializeRow( row( '   ', 'block', 'any' ) ) ).toBe( '' );
	} );

	it( 'drops a value-required qualifier left blank', () => {
		expect( serializeRow( row( 'x', 'block', 'country', '' ) ) ).toBe( '' );
		expect( serializeRow( row( 'x', 'block', 'asn', '' ) ) ).toBe( '' );
		expect( serializeRow( row( 'x', 'block', 'ref', '' ) ) ).toBe( '' );
	} );
} );

describe( 'serializeUaList', () => {
	it( 'joins rows with commas and drops blank/incomplete rows', () => {
		expect(
			serializeUaList( [
				row( 'GPTBot', 'block', 'any' ),
				row( '', 'block', 'any' ), // dropped (no UA)
				row( 'x', 'block', 'country', '' ), // dropped (blank value)
				row( 'Googlebot', 'pass', 'host' ),
			] )
		).toBe( 'GPTBot#*,Googlebot:HOST' );
	} );

	it( 'defaults to empty', () => {
		expect( serializeUaList() ).toBe( '' );
		expect( serializeUaList( [] ) ).toBe( '' );
	} );
} );

describe( 'round-trip', () => {
	const canonical =
		'Googlebot:HOST,bingbot:HOST,*:FEED,facebookexternalhit:*,GPTBot#*,Twitterbot:US,x:AS15169,y:REF=example.com,z:192.168.0.0/16,GPTBot#!US';

	it( 'serialize(parse(x)) preserves a canonical string', () => {
		expect( serializeUaList( parseUaList( canonical ) ) ).toBe( canonical );
	} );

	it( 'parse(serialize(rows)) preserves structure', () => {
		const rows = [
			row( 'Googlebot', 'pass', 'host' ),
			row( 'GPTBot', 'block', 'any' ),
			row( 'Twitterbot', 'pass', 'country', 'US' ),
		];
		expect( parseUaList( serializeUaList( rows ) ) ).toEqual( rows );
	} );
} );

describe( 'ruleKey', () => {
	it( 'is stable across equivalent case / whitespace', () => {
		expect( ruleKey( row( 'Googlebot', 'pass', 'host' ) ) ).toBe(
			ruleKey( parseUaList( 'Googlebot:host' )[ 0 ] )
		);
	} );
} );

describe( 'normalizeUaList', () => {
	it( 'collapses newlines and whitespace to a comma token string', () => {
		expect( normalizeUaList( 'a:HOST,\n b:US ,,c:*' ) ).toBe(
			'a:HOST,b:US,c:*'
		);
		expect( normalizeUaList( '' ) ).toBe( '' );
	} );
} );
