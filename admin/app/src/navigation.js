/**
 * Small URL helpers for deep-linking between Beta admin tabs without adding a
 * routing dependency. All links stay on the current wp-admin screen.
 */

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
