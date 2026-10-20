import { __, sprintf } from '@wordpress/i18n';

export const rot13 = ( value = '' ) =>
	String( value ).replace( /[a-z]/gi, ( character ) =>
		String.fromCharCode(
			character.charCodeAt( 0 ) +
				( character.toLowerCase() < 'n' ? 13 : -13 )
		)
	);

// UTF-8 safe base64: btoa() only accepts Latin-1, so feed it the UTF-8 bytes.
const toBase64 = ( value ) =>
	globalThis.btoa(
		encodeURIComponent( String( value ) ).replace(
			/%([0-9A-F]{2})/g,
			( match, hex ) => String.fromCharCode( parseInt( hex, 16 ) )
		)
	);

/**
 * Encode the bad-signature list for transport the way the classic admin did
 * (rot13, then base64). Web application firewalls (WordPress.com, ModSecurity)
 * reject request bodies containing `../`, `/wp-config.php` or `passwd`, which
 * is exactly what the default list holds.
 *
 * @param {string} value Readable signature list.
 * @return {string} Encoded value for `signature_encoded`.
 */
export const encodeSignature = ( value = '' ) => toBase64( rot13( value ) );

/**
 * Copy of a settings object with the readable `signature` replaced by
 * `signature_encoded`, so the raw list never travels in a request body.
 *
 * @param {Object} settings Settings draft.
 * @return {Object} Settings safe to POST.
 */
export const withEncodedSignature = ( settings ) => {
	if ( ! settings || typeof settings.signature !== 'string' ) {
		return settings;
	}
	const { signature, ...rest } = settings;
	return { ...rest, signature_encoded: encodeSignature( signature ) };
};

/**
 * Encode a whole imported settings file. Its format (classic flat keys or the
 * nested beta shape) and contents are arbitrary, so hide all of it from WAFs.
 *
 * @param {Object} data Parsed settings file.
 * @return {string} Base64 JSON for `data_encoded`.
 */
export const encodePayload = ( data ) => toBase64( JSON.stringify( data ) );

/**
 * Display text for a failed REST request: the server message, the request
 * that failed, and why a non-JSON answer usually happens.
 *
 * @param {Object} error    apiFetch rejection ({ code, message }).
 * @param {string} endpoint REST route that failed.
 * @return {string} Display-ready message.
 */
export const describeRequestError = ( error, endpoint = '' ) => {
	let text =
		error?.message || __( 'Unknown error.', 'ip-location-block' );
	if ( endpoint ) {
		text = sprintf(
			/* translators: 1: error message, 2: REST route, e.g. settings/context */
			__( '%1$s (request: %2$s)', 'ip-location-block' ),
			text,
			endpoint
		);
	}
	if ( error?.code === 'invalid_json' ) {
		text +=
			' ' +
			__(
				'The site answered with a page instead of data. A firewall, a security plugin or PHP output is changing REST API responses. Try the Classic view, and send this message to support if it keeps happening.',
				'ip-location-block'
			);
	}
	return text;
};

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

/**
 * Non-fatal warnings the server attaches to a successful POST /settings
 * response (e.g. an mu-plugin copy failure that silently reset validation
 * timing). Returns a clean, display-ready list; HTML tags in the message
 * (the server wraps paths in <code>…</code>) are stripped for plain-text
 * notices.
 *
 * @param {Object} response The POST /settings response object.
 * @return {Array<{code:string,message:string}>} Display-ready warnings.
 */
export const saveWarnings = ( response ) => {
	const list = Array.isArray( response?.warnings ) ? response.warnings : [];
	return list
		.filter( ( item ) => item && item.message )
		.map( ( item ) => ( {
			code: String( item.code || '' ),
			message: String( item.message ).replace( /<\/?[^>]+>/g, '' ),
		} ) );
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
