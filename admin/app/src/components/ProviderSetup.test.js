import { createRoot } from '@wordpress/element';
// React is supplied by @wordpress/element; act is only used by these tests.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { dismissNativePromo, testProvider } from '../api';
import ProviderSetup from './ProviderSetup';

jest.mock( '../api', () => ( {
	dismissNativePromo: jest.fn(),
	testProvider: jest.fn(),
} ) );

describe( 'Simple Mode Native suggestion journey', () => {
	let container;
	let root;
	let onChange;
	const providers = [
		{ name: 'IP2Location', local: true, auth: 'none', databaseReady: true },
		{ name: 'IP Location Block', auth: 'required' },
		{ name: 'ipinfo.io', auth: 'required' },
	];

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		window.ipLocationBlockAdmin = { nativePromoDismissed: false };
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		onChange = jest.fn();
		jest.clearAllMocks();
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		global.IS_REACT_ACT_ENVIRONMENT = false;
	} );

	const render = ( native = false ) => {
		const name = native ? 'IP Location Block' : 'IP2Location';
		act( () => {
			root.render(
				<ProviderSetup
					settings={ { providers: { [ name ]: '@' } } }
					providers={ providers }
					status={ {
						providers: [
							{
								name,
								active: true,
								ready: true,
								local: ! native,
							},
						],
					} }
					onChange={ onChange }
				/>
			);
		} );
	};

	it( 'selects Native Mode and focuses its key field without changing providers', () => {
		render();
		const chooser = container.querySelector( '.ilb-provider-alternatives' );
		const select = chooser.querySelector( 'select' );
		act( () => {
			select.value = 'ipinfo.io';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		act( () =>
			container.querySelector( '.ilb-provider-promo .is-link' ).click()
		);
		expect( chooser.open ).toBe( true );
		expect( select.value ).toBe( 'IP Location Block' );
		expect( document.activeElement ).toBe(
			chooser.querySelector( 'input' )
		);
		expect( onChange ).not.toHaveBeenCalled();
		expect( testProvider ).not.toHaveBeenCalled();
	} );

	it( 'focuses the chooser after dismissal and preserves the open key-entry form', async () => {
		render();
		act( () =>
			container.querySelector( '.ilb-provider-promo .is-link' ).click()
		);
		const chooser = container.querySelector( '.ilb-provider-alternatives' );
		const input = chooser.querySelector( 'input' );
		dismissNativePromo.mockResolvedValueOnce( { dismissed: true } );
		await act( async () => {
			container.querySelector( '.ilb-provider-promo__dismiss' ).click();
		} );
		expect( container.querySelector( '.ilb-provider-promo' ) ).toBeNull();
		expect( document.activeElement ).toBe(
			chooser.querySelector( 'summary' )
		);
		expect( chooser.open ).toBe( true );
		expect( chooser.querySelector( 'input' ) ).toBe( input );
		expect( chooser.textContent ).toContain( 'Test and connect' );
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'keeps the suggestion hidden when Native Mode is active', () => {
		render( true );
		expect( container.querySelector( '.ilb-provider-promo' ) ).toBeNull();
		expect( container.textContent ).toContain( 'Replace key' );
	} );
} );
