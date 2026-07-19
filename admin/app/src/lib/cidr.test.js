import { cidrToRange, rangeToCidr, isValidIp } from './cidr';

describe( 'isValidIp', () => {
	it.each( [ '192.168.0.1', '0.0.0.0', '255.255.255.255', '::1', '2001:db8::', '::ffff:192.168.0.1' ] )(
		'accepts %s',
		( ip ) => expect( isValidIp( ip ) ).toBe( true )
	);
	it.each( [ '', '999.0.0.1', '1.2.3', '1.2.3.4.5', 'nope', '2001:::1', 'gggg::' ] )(
		'rejects %s',
		( ip ) => expect( isValidIp( ip ) ).toBe( false )
	);
} );

describe( 'cidrToRange (IPv4)', () => {
	it( '192.168.0.0/16', () => {
		expect( cidrToRange( '192.168.0.0/16' ) ).toMatchObject( {
			version: 4,
			start: '192.168.0.0',
			end: '192.168.255.255',
		} );
	} );
	it( 'normalizes a non-aligned base to the network address', () => {
		expect( cidrToRange( '192.168.5.130/24' ) ).toMatchObject( {
			start: '192.168.5.0',
			end: '192.168.5.255',
		} );
	} );
	it( '10.0.0.1/32 is a single host', () => {
		expect( cidrToRange( '10.0.0.1/32' ) ).toMatchObject( { start: '10.0.0.1', end: '10.0.0.1' } );
	} );
	it( '0.0.0.0/0 covers everything', () => {
		expect( cidrToRange( '0.0.0.0/0' ) ).toMatchObject( { start: '0.0.0.0', end: '255.255.255.255' } );
	} );
	it( 'rejects a prefix out of range', () => {
		expect( cidrToRange( '10.0.0.0/33' ) ).toBeNull();
	} );
} );

describe( 'cidrToRange (IPv6)', () => {
	it( '2001:db8::/32', () => {
		const r = cidrToRange( '2001:db8::/32' );
		expect( r.version ).toBe( 6 );
		expect( r.start ).toBe( '2001:db8::' );
		expect( r.end ).toBe( '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff' );
	} );
	it( '::/0 covers everything', () => {
		expect( cidrToRange( '::/0' ) ).toMatchObject( {
			start: '::',
			end: 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff',
		} );
	} );
} );

describe( 'rangeToCidr (IPv4)', () => {
	it( 'exact block -> single CIDR', () => {
		expect( rangeToCidr( '192.168.0.0', '192.168.255.255' ) ).toEqual( [ '192.168.0.0/16' ] );
	} );
	it( 'single address -> /32', () => {
		expect( rangeToCidr( '10.0.0.5', '10.0.0.5' ) ).toEqual( [ '10.0.0.5/32' ] );
	} );
	it( 'non-aligned range -> minimal block list', () => {
		// The canonical example: 0.0.0.0-0.0.0.2 => /31 + /32
		expect( rangeToCidr( '0.0.0.0', '0.0.0.2' ) ).toEqual( [ '0.0.0.0/31', '0.0.0.2/32' ] );
	} );
	it( 'whole space', () => {
		expect( rangeToCidr( '0.0.0.0', '255.255.255.255' ) ).toEqual( [ '0.0.0.0/0' ] );
	} );
	it( 'rejects reversed range', () => {
		expect( rangeToCidr( '10.0.0.2', '10.0.0.1' ) ).toBeNull();
	} );
	it( 'rejects mixed families', () => {
		expect( rangeToCidr( '10.0.0.0', '::1' ) ).toBeNull();
	} );
} );

describe( 'round-trip', () => {
	it( 'cidrToRange -> rangeToCidr recovers the block', () => {
		const r = cidrToRange( '172.16.0.0/12' );
		expect( rangeToCidr( r.start, r.end ) ).toEqual( [ '172.16.0.0/12' ] );
	} );
	it( 'IPv6 block round-trips', () => {
		const r = cidrToRange( '2001:db8:abcd::/48' );
		expect( rangeToCidr( r.start, r.end ) ).toEqual( [ '2001:db8:abcd::/48' ] );
	} );
} );
