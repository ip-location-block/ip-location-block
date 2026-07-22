/**
 * Small URL helpers for deep-linking between admin tabs without adding a
 * routing dependency. All links stay on the current wp-admin screen.
 */

/**
 * Legacy classic `tab=<number>` deep links mapped to React tab names. Attribution
 * (classic tab 3) lives in the footer modal now, so it lands on Settings.
 */
export const LEGACY_TAB_MAP = {
	0: 'settings',
	1: 'statistics',
	2: 'search',
	3: 'settings',
	4: 'logs',
	5: 'sites',
};

/**
 * Normalize a requested `tab` value (string name or legacy numeric) to a React
 * tab name. Returns '' when nothing usable was requested.
 */
export const resolveTabName = ( requested ) => {
	if ( requested === null || requested === undefined || requested === '' ) {
		return '';
	}
	const value = String( requested );
	if ( /^\d+$/.test( value ) ) {
		return LEGACY_TAB_MAP[ Number( value ) ] || '';
	}
	return value;
};

export const queryParam = ( key ) => {
	try {
		return (
			new window.URL( window.location.href ).searchParams.get( key ) || ''
		);
	} catch {
		return '';
	}
};

export const betaUrl = ( params = {} ) => {
	const url = new window.URL( window.location.href );
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value === null || value === undefined || value === '' ) {
			url.searchParams.delete( key );
		} else {
			url.searchParams.set( key, String( value ) );
		}
	} );
	return url.toString();
};

/**
 * Build a self-persisting view-switch URL from the CURRENT admin URL (so the
 * live tab is preserved), setting the target view and the switch nonce.
 */
export const switchViewUrl = ( view, nonce ) => {
	try {
		const url = new window.URL( window.location.href );
		url.searchParams.set( 'view', view );
		if ( nonce ) {
			url.searchParams.set( '_wpnonce', nonce );
		}
		return url.toString();
	} catch {
		return '';
	}
};

export const replaceTabInUrl = ( tab ) => {
	try {
		const url = new window.URL( window.location.href );
		const previous = url.searchParams.get( 'tab' );
		url.searchParams.set( 'tab', tab );
		if ( previous && previous !== tab ) {
			url.searchParams.delete( 's' );
			url.searchParams.delete( 'view' );
			url.searchParams.delete( 'section' );
		}
		window.history.replaceState( {}, '', url.toString() );
	} catch {
		// URL/history access can be unavailable in embedded admin contexts.
	}
};
