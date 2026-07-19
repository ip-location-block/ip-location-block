import { queryParam, betaUrl, replaceTabInUrl } from './navigation';

describe( 'Beta admin navigation', () => {
	beforeEach( () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/options-general.php?page=ip-location-block-beta&tab=statistics&s=US'
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
		expect( url.searchParams.get( 'page' ) ).toBe(
			'ip-location-block-beta'
		);
		expect( url.searchParams.get( 'tab' ) ).toBe( 'logs' );
		expect( url.searchParams.get( 's' ) ).toBe( 'AS123' );
	} );

	it( 'removes stale detail parameters when tabs change', () => {
		replaceTabInUrl( 'search' );
		expect( queryParam( 'tab' ) ).toBe( 'search' );
		expect( queryParam( 's' ) ).toBe( '' );
	} );
} );
