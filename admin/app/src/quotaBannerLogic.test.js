import {
	dismissQuotaBanner,
	incidentKey,
	isDismissed,
	quotaBlocking,
	shouldShowBanner,
} from './quotaBannerLogic';

// Minimal in-memory Storage stand-in so tests never touch real localStorage.
const makeStorage = () => {
	const map = new Map();
	return {
		getItem: ( key ) => ( map.has( key ) ? map.get( key ) : null ),
		setItem: ( key, value ) => map.set( key, String( value ) ),
		removeItem: ( key ) => map.delete( key ),
	};
};

const exhausted = {
	status: 'exhausted',
	planName: 'Starter',
	total: 0,
	message: 'The account has no remaining requests.',
};

describe( 'quota banner logic', () => {
	test( 'only blocking statuses count', () => {
		expect( quotaBlocking( exhausted ) ).toBe( true );
		expect( quotaBlocking( { status: 'key_upgrade_required' } ) ).toBe(
			true
		);
		expect( quotaBlocking( { status: 'rate_limited' } ) ).toBe( true );
		expect( quotaBlocking( { status: 'ok' } ) ).toBe( false );
		expect( quotaBlocking( null ) ).toBe( false );
	} );

	test( 'incident key is empty for non-blocking quota', () => {
		expect( incidentKey( { status: 'ok' } ) ).toBe( '' );
		expect( incidentKey( null ) ).toBe( '' );
	} );

	test( 'incident key changes when the situation changes', () => {
		const upgrade = { status: 'key_upgrade_required', planName: 'Starter' };
		expect( incidentKey( exhausted ) ).not.toBe( incidentKey( upgrade ) );
	} );

	test( 'incident key is stable across refreshes (ignores checkedAt)', () => {
		expect( incidentKey( { ...exhausted, checkedAt: 1 } ) ).toBe(
			incidentKey( { ...exhausted, checkedAt: 999 } )
		);
	} );

	test( 'shows, hides after dismiss, then re-shows for a NEW incident', () => {
		const storage = makeStorage();

		// Initially shown.
		expect( shouldShowBanner( exhausted, storage ) ).toBe( true );

		// Dismiss the current incident -> hidden.
		dismissQuotaBanner( exhausted, storage );
		expect( isDismissed( exhausted, storage ) ).toBe( true );
		expect( shouldShowBanner( exhausted, storage ) ).toBe( false );

		// The SAME incident stays dismissed even after a quota refresh.
		expect(
			shouldShowBanner( { ...exhausted, checkedAt: 42 }, storage )
		).toBe( false );

		// A NEW incident (different status) re-shows the banner.
		const upgrade = {
			status: 'key_upgrade_required',
			planName: 'Starter',
			message: 'This API key must be upgraded before it can be used.',
		};
		expect( shouldShowBanner( upgrade, storage ) ).toBe( true );
	} );

	test( 'nothing to show is treated as already handled', () => {
		const storage = makeStorage();
		expect( shouldShowBanner( { status: 'ok' }, storage ) ).toBe( false );
		expect( isDismissed( { status: 'ok' }, storage ) ).toBe( true );
	} );
} );
