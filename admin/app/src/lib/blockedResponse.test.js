import {
	isRedirectResponse,
	redirectDestinationStatus,
	sameRedirectDestination,
} from './blockedResponse';

const SITE_URL = 'https://www.example.com/';

describe( 'blocked response helpers', () => {
	test.each( [ 300, 301, 302, 303, 307, 308, '307' ] )(
		'recognizes %s as a redirect response',
		( code ) => expect( isRedirectResponse( code ) ).toBe( true )
	);

	test.each( [ 200, 299, 400, 403, 500, null ] )(
		'does not treat %s as a redirect response',
		( code ) => expect( isRedirectResponse( code ) ).toBe( false )
	);

	test.each( [
		'https://blocked.iplocationblock.com/',
		'https://blocked.example.com/',
		'https://example.net/blocked?from=wordpress',
		'http://blocked.example.com:8080/',
	] )( 'accepts a separate HTTP destination: %s', ( destination ) => {
		expect(
			redirectDestinationStatus( destination, SITE_URL )
		).toMatchObject( {
			valid: true,
			reason: null,
		} );
	} );

	test.each( [
		'https://www.example.com/blocked',
		'https://WWW.EXAMPLE.COM:8443/blocked',
		'https://www.example.com./blocked',
	] )( 'rejects the protected hostname: %s', ( destination ) => {
		expect(
			redirectDestinationStatus( destination, SITE_URL )
		).toMatchObject( {
			valid: false,
			reason: 'same_host',
		} );
	} );

	test.each( [
		'',
		'/blocked',
		'blocked.example.com',
		'javascript:alert(1)',
		'ftp://blocked.example.com/',
		'https://user:secret@blocked.example.com/',
		'not a URL',
	] )( 'rejects a non-absolute web destination: %s', ( destination ) => {
		expect( redirectDestinationStatus( destination, SITE_URL ).valid ).toBe(
			false
		);
	} );

	test( 'compares canonical destination URLs', () => {
		expect(
			sameRedirectDestination(
				'https://blocked.iplocationblock.com',
				'https://blocked.iplocationblock.com/'
			)
		).toBe( true );
		expect(
			sameRedirectDestination(
				'https://blocked.iplocationblock.com/',
				'https://blocked.example.com/'
			)
		).toBe( false );
	} );
} );
