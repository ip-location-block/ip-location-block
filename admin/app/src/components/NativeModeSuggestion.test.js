import { createRoot } from '@wordpress/element';
// React is supplied by @wordpress/element; act is only used by these tests.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { dismissNativePromo } from '../api';
import NativeModeSuggestion from './NativeModeSuggestion';

jest.mock( '../api', () => ( { dismissNativePromo: jest.fn() } ) );

describe( 'NativeModeSuggestion', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		window.ipLocationBlockAdmin = { nativePromoDismissed: false };
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		jest.clearAllMocks();
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		global.IS_REACT_ACT_ENVIRONMENT = false;
	} );

	const render = ( props = {} ) => {
		act( () => {
			root.render( <NativeModeSuggestion { ...props } /> );
		} );
	};

	const dismiss = () =>
		act( async () => {
			container.querySelector( '.ilb-provider-promo__dismiss' ).click();
		} );

	it( 'opens setup only when requested and respects pending provider changes', () => {
		const onConnect = jest.fn();
		render( { onConnect } );
		const link = container.querySelector( 'a' );
		expect( link.href ).toBe(
			'https://iplocationblock.com/docs/blocking-rules/state-region/'
		);
		expect( onConnect ).not.toHaveBeenCalled();
		act( () => container.querySelector( 'button.is-link' ).click() );
		expect( onConnect ).toHaveBeenCalledTimes( 1 );
		render( { onConnect, connectDisabled: true } );
		expect( container.querySelector( 'button.is-link' ).disabled ).toBe(
			true
		);
	} );

	it( 'keeps the card available after a failed dismissal so it can be retried', async () => {
		dismissNativePromo.mockRejectedValueOnce(
			new Error( 'Network error' )
		);
		const onDismiss = jest.fn();
		render( { onDismiss } );
		await dismiss();
		expect(
			container.querySelector( '[role="alert"]' ).textContent
		).toContain( 'Please try again.' );
		expect(
			container.querySelector( '.ilb-provider-promo__dismiss' ).disabled
		).toBe( false );
		expect( onDismiss ).not.toHaveBeenCalled();
	} );

	it( 'does not hide the card when the server does not confirm persistence', async () => {
		dismissNativePromo.mockResolvedValueOnce( { dismissed: false } );
		render();
		await dismiss();
		expect(
			container.querySelector( '.ilb-provider-promo' )
		).not.toBeNull();
		expect( container.querySelector( '[role="alert"]' ) ).not.toBeNull();
	} );

	it( 'waits for persistence, calls the focus handler, and remains hidden after remount', async () => {
		let resolve;
		dismissNativePromo.mockReturnValueOnce(
			new Promise( ( done ) => {
				resolve = done;
			} )
		);
		const onDismiss = jest.fn();
		render( { onDismiss } );
		await dismiss();
		expect(
			container.querySelector( '.ilb-provider-promo' )
		).not.toBeNull();
		expect(
			container.querySelector( '.ilb-provider-promo__dismiss' ).disabled
		).toBe( true );
		await act( async () => resolve( { dismissed: true } ) );
		expect( onDismiss ).toHaveBeenCalledTimes( 1 );
		expect( container.querySelector( '.ilb-provider-promo' ) ).toBeNull();
		act( () => root.render( null ) );
		render();
		expect( container.querySelector( '.ilb-provider-promo' ) ).toBeNull();
	} );

	it( 'honors the server preference on the first render', () => {
		window.ipLocationBlockAdmin.nativePromoDismissed = true;
		render();
		expect( container.querySelector( '.ilb-provider-promo' ) ).toBeNull();
		expect( dismissNativePromo ).not.toHaveBeenCalled();
	} );
} );
