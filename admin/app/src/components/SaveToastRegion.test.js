import { createRoot, flushSync } from '@wordpress/element';

import SaveToastRegion from './SaveToastRegion';

describe( 'SaveToastRegion', () => {
	let container;
	let root;

	beforeEach( () => {
		jest.useFakeTimers();
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		flushSync( () => root.unmount() );
		container.remove();
		jest.useRealTimers();
	} );

	const render = ( notices, onRemove ) => {
		flushSync( () => {
			root.render(
				<SaveToastRegion notices={ notices } onRemove={ onRemove } />
			);
		} );
	};

	it( 'removes a timed success notice after ten seconds', () => {
		const onRemove = jest.fn();
		render(
			[
				{
					id: 'success-1',
					status: 'success',
					message: 'Settings saved.',
					persistent: false,
				},
			],
			onRemove
		);

		expect( container.querySelector( '.is-timed' ) ).not.toBeNull();
		jest.advanceTimersByTime( 9999 );
		expect( onRemove ).not.toHaveBeenCalled();
		jest.advanceTimersByTime( 1 );
		expect( onRemove ).toHaveBeenCalledWith( 'success-1' );
	} );

	it( 'keeps warnings and errors until their close control is used', () => {
		const onRemove = jest.fn();
		render(
			[
				{
					id: 'warning-1',
					status: 'warning',
					message: 'Review this warning.',
					persistent: true,
				},
				{
					id: 'error-1',
					status: 'error',
					message: 'Save failed.',
					persistent: true,
				},
			],
			onRemove
		);

		jest.advanceTimersByTime( 20000 );
		expect( onRemove ).not.toHaveBeenCalled();
		expect( container.querySelectorAll( '.is-warning' ) ).toHaveLength( 1 );
		expect( container.querySelectorAll( '.is-error' ) ).toHaveLength( 1 );

		container
			.querySelector( '.components-snackbar__dismiss-button' )
			.dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
		expect( onRemove ).toHaveBeenCalledWith( 'warning-1' );
	} );

	it( 'stacks multiple warnings ahead of the success confirmation', () => {
		const onRemove = jest.fn();
		render(
			[
				{
					id: 'warning-1',
					status: 'warning',
					message: 'First warning.',
					persistent: true,
				},
				{
					id: 'warning-2',
					status: 'warning',
					message: 'Second warning.',
					persistent: true,
				},
				{
					id: 'success-1',
					status: 'success',
					message: 'Settings saved.',
					persistent: false,
				},
			],
			onRemove
		);

		const notices = container.querySelectorAll( '.components-snackbar' );
		expect( notices ).toHaveLength( 3 );
		expect( notices[ 0 ].classList.contains( 'is-warning' ) ).toBe( true );
		expect( notices[ 1 ].classList.contains( 'is-warning' ) ).toBe( true );
		expect( notices[ 2 ].classList.contains( 'is-success' ) ).toBe( true );
		expect( notices[ 2 ].classList.contains( 'is-timed' ) ).toBe( true );
	} );
} );
