import {
	queryParam,
	betaUrl,
	replaceTabInUrl,
	resolveTabName,
	switchViewUrl,
} from './navigation';

describe( 'admin navigation', () => {
	beforeEach( () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/options-general.php?page=ip-location-block&tab=statistics&s=US'
		);
	} );

	it( 'reads query parameters', () => {
		expect( queryParam( 'tab' ) ).toBe( 'statistics' );
		expect( queryParam( 'missing' ) ).toBe( '' );
	} );

	it( 'builds links on the current admin screen', () => {
		const url = new URL(
			betaUrl( { tab: 'logs', s: 'AS123', section: null } )
		);
		expect( url.pathname ).toBe( '/wp-admin/options-general.php' );
		expect( url.searchParams.get( 'page' ) ).toBe( 'ip-location-block' );
		expect( url.searchParams.get( 'tab' ) ).toBe( 'logs' );
		expect( url.searchParams.get( 's' ) ).toBe( 'AS123' );
	} );

	it( 'removes stale detail parameters when tabs change', () => {
		replaceTabInUrl( 'search' );
		expect( queryParam( 'tab' ) ).toBe( 'search' );
		expect( queryParam( 's' ) ).toBe( '' );
	} );
} );

describe( 'resolveTabName', () => {
	it( 'maps legacy classic numeric tabs to React tab names', () => {
		expect( resolveTabName( '0' ) ).toBe( 'settings' );
		expect( resolveTabName( '1' ) ).toBe( 'statistics' );
		expect( resolveTabName( '2' ) ).toBe( 'search' );
		expect( resolveTabName( '3' ) ).toBe( 'settings' ); // attribution -> footer modal
		expect( resolveTabName( '4' ) ).toBe( 'logs' );
		expect( resolveTabName( '5' ) ).toBe( 'sites' );
		expect( resolveTabName( 4 ) ).toBe( 'logs' );
	} );

	it( 'passes React tab names through unchanged', () => {
		expect( resolveTabName( 'logs' ) ).toBe( 'logs' );
		expect( resolveTabName( 'diagnostics' ) ).toBe( 'diagnostics' );
	} );

	it( 'returns empty for unknown numbers or empty input', () => {
		expect( resolveTabName( '99' ) ).toBe( '' );
		expect( resolveTabName( '' ) ).toBe( '' );
		expect( resolveTabName( null ) ).toBe( '' );
		expect( resolveTabName( undefined ) ).toBe( '' );
	} );
} );

describe( 'switchViewUrl', () => {
	beforeEach( () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/options-general.php?page=ip-location-block&tab=logs'
		);
	} );

	it( 'sets the target view + nonce while preserving the live tab', () => {
		const url = new URL( switchViewUrl( 'classic', 'abc123' ) );
		expect( url.searchParams.get( 'page' ) ).toBe( 'ip-location-block' );
		expect( url.searchParams.get( 'tab' ) ).toBe( 'logs' );
		expect( url.searchParams.get( 'view' ) ).toBe( 'classic' );
		expect( url.searchParams.get( '_wpnonce' ) ).toBe( 'abc123' );
	} );

	it( 'omits the nonce param when none is supplied', () => {
		const url = new URL( switchViewUrl( 'new' ) );
		expect( url.searchParams.get( 'view' ) ).toBe( 'new' );
		expect( url.searchParams.has( '_wpnonce' ) ).toBe( false );
	} );
} );
