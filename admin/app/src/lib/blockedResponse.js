export const isRedirectResponse = ( code ) => {
	const value = Number( code );
	return value >= 300 && value < 400;
};

const hostname = ( url ) =>
	String( url?.hostname || '' )
		.toLowerCase()
		.replace( /\.$/, '' );

export const redirectDestinationStatus = ( value, protectedSiteUrl ) => {
	const input = String( value || '' ).trim();
	if ( ! input ) {
		return { valid: false, reason: 'empty', url: null };
	}

	let destination;
	try {
		destination = new URL( input );
	} catch {
		return { valid: false, reason: 'absolute_url', url: null };
	}

	if (
		! [ 'http:', 'https:' ].includes( destination.protocol ) ||
		! hostname( destination ) ||
		destination.username ||
		destination.password
	) {
		return { valid: false, reason: 'absolute_url', url: null };
	}

	let protectedSite = null;
	try {
		protectedSite = new URL( protectedSiteUrl );
	} catch {
		// The localized site URL is supplied by WordPress. If it is unexpectedly
		// unavailable, keep scheme validation useful without rejecting every URL.
	}

	if (
		protectedSite &&
		hostname( destination ) === hostname( protectedSite )
	) {
		return { valid: false, reason: 'same_host', url: destination };
	}

	return { valid: true, reason: null, url: destination };
};

export const sameRedirectDestination = ( left, right ) => {
	try {
		return new URL( left ).href === new URL( right ).href;
	} catch {
		return false;
	}
};
