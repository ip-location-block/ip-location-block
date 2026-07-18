import { decodeLegacySignature, rot13, selectedMimeMap } from './settingsLogic';

describe( 'settings logic', () => {
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
} );
