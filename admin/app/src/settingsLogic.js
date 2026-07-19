export const rot13 = ( value = '' ) =>
	String( value ).replace( /[a-z]/gi, ( character ) =>
		String.fromCharCode(
			character.charCodeAt( 0 ) +
				( character.toLowerCase() < 'n' ? 13 : -13 )
		)
	);

export const decodeLegacySignature = ( value = '' ) => {
	const input = String( value ).trim();
	if ( ! input || input.includes( ',' ) || input.includes( '\n' ) ) {
		return null;
	}
	try {
		const decode = globalThis.atob;
		if ( typeof decode !== 'function' ) {
			return null;
		}
		const decoded = rot13( decode( input ) );
		if (
			! decoded ||
			! /[,/]/.test( decoded ) ||
			/[\u0000-\u0008\u000e-\u001f]/.test( decoded )
		) {
			return null;
		}
		return decoded;
	} catch {
		return null;
	}
};

export const selectedMimeMap = ( catalog = [], selected = {} ) => {
	const allowed = new Map(
		catalog.map( ( item ) => [ item.extension, item.mime ] )
	);
	return Object.entries( selected || {} ).reduce(
		( result, [ key, mime ] ) => {
			result[ key ] = allowed.get( key ) || mime;
			return result;
		},
		{}
	);
};
