import {
	configuredProviders,
	defaultAnonymize,
	defaultSelectedProviders,
	maskIp,
} from './searchLogic';

const providers = [
	{ name: 'IP Location Block', selected: true, local: false },
	{ name: 'IP2Location', selected: true, local: true, databaseReady: true },
	{ name: 'GeoLite2', selected: true, local: true, databaseReady: false },
	{ name: 'ipstack', selected: false, local: false },
];

describe( 'search logic', () => {
	test( 'offers only configured providers, marking unready local ones disabled', () => {
		expect( configuredProviders( providers ) ).toEqual( [
			{ name: 'IP Location Block', disabled: false, reason: '' },
			{ name: 'IP2Location', disabled: false, reason: '' },
			{
				name: 'GeoLite2',
				disabled: true,
				reason: 'database_missing',
			},
		] );
	} );

	test( 'default selection excludes unready providers', () => {
		expect( defaultSelectedProviders( providers ) ).toEqual( [
			'IP Location Block',
			'IP2Location',
		] );
	} );

	test( 'anonymize defaults on when the anonymize setting is truthy', () => {
		expect( defaultAnonymize( { anonymize: 1 } ) ).toBe( true );
		expect( defaultAnonymize( { anonymize: 0 } ) ).toBe( false );
		expect( defaultAnonymize( {} ) ).toBe( false );
		expect( defaultAnonymize( undefined ) ).toBe( false );
	} );

	test( 'maskIp mirrors Util::anonymize_ip strict masking', () => {
		expect( maskIp( '8.8.8.8' ) ).toBe( '8.8.8.***' );
		expect( maskIp( '203.0.113.42' ) ).toBe( '203.0.113.***' );
		expect( maskIp( '' ) ).toBe( '' );
	} );
} );
