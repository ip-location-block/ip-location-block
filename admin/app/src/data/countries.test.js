import { expandTokens, EU_CODES, EU_TOKEN, codeFromToken } from './countries';

describe( 'EU_CODES', () => {
	it( 'is the 27 EU member states, de-duplicated', () => {
		expect( EU_CODES ).toHaveLength( 27 );
		expect( new Set( EU_CODES ).size ).toBe( 27 );
		// A few anchors — includes Croatia (2013) and excludes the UK.
		expect( EU_CODES ).toEqual(
			expect.arrayContaining( [ 'HR', 'FR', 'DE', 'ES', 'SE' ] )
		);
		expect( EU_CODES ).not.toContain( 'GB' );
	} );
} );

describe( 'expandTokens', () => {
	it( 'expands the EU token to the 27 member codes', () => {
		expect( expandTokens( [ EU_TOKEN ] ) ).toEqual( EU_CODES );
	} );

	it( 'expands a bare "EU" too', () => {
		expect( expandTokens( [ 'eu' ] ) ).toEqual( EU_CODES );
	} );

	it( 'de-dupes EU members already present, preserving order', () => {
		const out = expandTokens( [ '🇫🇷 France (FR)', EU_TOKEN ] );
		expect( out[ 0 ] ).toBe( 'FR' );
		expect( out ).toHaveLength( 27 ); // FR not duplicated
		expect( out.filter( ( c ) => c === 'FR' ) ).toHaveLength( 1 );
	} );

	it( 'keeps non-EU selections alongside the expansion', () => {
		const out = expandTokens( [ 'US', EU_TOKEN, 'JP' ] );
		expect( out ).toContain( 'US' );
		expect( out ).toContain( 'JP' );
		expect( out ).toEqual( expect.arrayContaining( EU_CODES ) );
		expect( out ).toHaveLength( 29 ); // 27 + US + JP
	} );

	it( 'drops unrecognized tokens', () => {
		expect( expandTokens( [ 'not-a-country', '' ] ) ).toEqual( [] );
	} );

	it( 'agrees with codeFromToken for plain country labels', () => {
		expect( expandTokens( [ '🇺🇸 United States (US)' ] ) ).toEqual( [
			codeFromToken( '🇺🇸 United States (US)' ),
		] );
	} );
} );
