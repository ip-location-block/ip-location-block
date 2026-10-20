import {
	decodeLegacySignature,
	describeRequestError,
	encodePayload,
	encodeSignature,
	rot13,
	saveWarnings,
	selectedMimeMap,
	withEncodedSignature,
} from './settingsLogic';

// Server side: str_rot13( base64_decode( $value ) ) over UTF-8 bytes.
const decodeLikeServer = ( value ) =>
	rot13(
		decodeURIComponent(
			Array.from( globalThis.atob( value ), ( character ) =>
				'%' + character.charCodeAt( 0 ).toString( 16 ).padStart( 2, '0' )
			).join( '' )
		)
	);

describe( 'settings logic', () => {
	test( 'encodes signatures in the classic transport format', () => {
		expect( encodeSignature( '../,/wp-config.php,/passwd' ) ).toBe(
			'Li4vLC9qYy1wYmFzdnQuY3VjLC9jbmZmanE='
		);
		expect(
			decodeLegacySignature(
				encodeSignature( '../,/wp-config.php,/passwd' )
			)
		).toBe( '../,/wp-config.php,/passwd' );
	} );

	test( 'encoded signatures survive non-ASCII text', () => {
		const value = 'select:.5,ünïcode:.4\ncurl';
		expect( decodeLikeServer( encodeSignature( value ) ) ).toBe( value );
	} );

	test( 'never sends the readable signature in a settings body', () => {
		const body = withEncodedSignature( {
			signature: '../,/wp-config.php,/passwd',
			matching_rule: 1,
		} );
		expect( body ).toEqual( {
			matching_rule: 1,
			signature_encoded: 'Li4vLC9qYy1wYmFzdnQuY3VjLC9jbmZmanE=',
		} );
		expect( JSON.stringify( body ) ).not.toMatch( /\.\.\/|wp-config|passwd/ );
		expect( withEncodedSignature( { matching_rule: 1 } ) ).toEqual( {
			matching_rule: 1,
		} );
	} );

	test( 'encodes a whole settings file for import', () => {
		const file = {
			'ip_location_block_settings[signature]': '../,/passwd',
		};
		const encoded = encodePayload( file );
		expect( encoded ).not.toMatch( /\.\.\/|passwd/ );
		expect( JSON.parse( globalThis.atob( encoded ) ) ).toEqual( file );
	} );

	test( 'names the failed request and explains non-JSON answers', () => {
		expect(
			describeRequestError(
				{
					code: 'invalid_json',
					message: 'The response is not a valid JSON response.',
				},
				'settings/context'
			)
		).toMatch(
			/^The response is not a valid JSON response\. \(request: settings\/context\) The site answered with a page instead of data\./
		);
		expect(
			describeRequestError( { code: 'rest_forbidden', message: 'Nope.' } )
		).toBe( 'Nope.' );
		expect( describeRequestError( undefined ) ).toBe( 'Unknown error.' );
	} );

	test( 'restores the classic encoded signature format', () => {
		expect(
			decodeLegacySignature( 'Li4vLC9qYy1wYmFzdnQuY3VjLC9jbmZmanE=' )
		).toBe( '../,/wp-config.php,/passwd' );
	} );

	test( 'does not reinterpret an ordinary readable signature', () => {
		expect( decodeLegacySignature( '../,/wp-config.php' ) ).toBeNull();
		expect( decodeLegacySignature( 'not base64' ) ).toBeNull();
	} );

	test( 'rot13 is reversible', () => {
		expect( rot13( rot13( 'IP Location Block' ) ) ).toBe(
			'IP Location Block'
		);
	} );

	test( 'keeps selected MIME entries and refreshes catalog values', () => {
		expect(
			selectedMimeMap(
				[ { extension: 'jpg|jpeg', mime: 'image/jpeg' } ],
				{
					'jpg|jpeg': 'old/value',
					custom: 'application/custom',
				}
			)
		).toEqual( {
			'jpg|jpeg': 'image/jpeg',
			custom: 'application/custom',
		} );
	} );

	test( 'extracts save warnings and strips HTML from the message', () => {
		expect(
			saveWarnings( {
				matching_rule: 0,
				warnings: [
					{
						code: 'validation-timing',
						message:
							'Unable to write in <code>/path/mu.php</code>. Please check your file system permissions.',
					},
				],
			} )
		).toEqual( [
			{
				code: 'validation-timing',
				message:
					'Unable to write in /path/mu.php. Please check your file system permissions.',
			},
		] );
	} );

	test( 'returns an empty list when there are no warnings', () => {
		expect( saveWarnings( { matching_rule: 0 } ) ).toEqual( [] );
		expect( saveWarnings( { warnings: [] } ) ).toEqual( [] );
		expect( saveWarnings( undefined ) ).toEqual( [] );
	} );

	test( 'drops malformed warning entries', () => {
		expect(
			saveWarnings( { warnings: [ null, {}, { message: 'ok' } ] } )
		).toEqual( [ { code: '', message: 'ok' } ] );
	} );
} );
