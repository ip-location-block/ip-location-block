import { SECTIONS } from './settingsSchema';

describe( 'Advanced settings documentation', () => {
	it( 'gives every Advanced section a unique current guide', () => {
		expect( SECTIONS ).toHaveLength( 7 );

		const paths = SECTIONS.map( ( section ) => section.docsPath );
		expect( new Set( paths ).size ).toBe( SECTIONS.length );
		paths.forEach( ( path ) => {
			expect( path ).toMatch( /^advanced-settings\/[a-z0-9-]+\/$/ );
		} );
	} );
} );
