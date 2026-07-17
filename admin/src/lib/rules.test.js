import { parseRules, serializeRules } from './rules';

describe( 'parseRules', () => {
	it( 'returns empty for falsy / non-string input', () => {
		expect( parseRules( '' ) ).toEqual( { countries: [], precise: [] } );
		expect( parseRules( null ) ).toEqual( { countries: [], precise: [] } );
		expect( parseRules( undefined ) ).toEqual( { countries: [], precise: [] } );
	} );

	it( 'parses plain country codes (upper-cased, blanks skipped)', () => {
		expect( parseRules( 'us, ca ,,fr' ) ).toEqual( {
			countries: [ 'US', 'CA', 'FR' ],
			precise: [],
		} );
	} );

	it( 'parses 3-part State and City rules', () => {
		expect( parseRules( 'US:State:California,US:City:Seattle' ) ).toEqual( {
			countries: [],
			precise: [
				{ country: 'US', level: 'State', value: 'California' },
				{ country: 'US', level: 'City', value: 'Seattle' },
			],
		} );
	} );

	it( 'treats a 2-part rule as a city shorthand', () => {
		expect( parseRules( 'FR:Paris' ) ).toEqual( {
			countries: [],
			precise: [ { country: 'FR', level: 'City', value: 'Paris' } ],
		} );
	} );

	it( 'mixes countries and precise rules', () => {
		const out = parseRules( 'CN,US:State:California,RU' );
		expect( out.countries ).toEqual( [ 'CN', 'RU' ] );
		expect( out.precise ).toEqual( [ { country: 'US', level: 'State', value: 'California' } ] );
	} );

	it( 'keeps multi-word place names', () => {
		expect( parseRules( 'US:State:New York' ).precise[ 0 ].value ).toBe( 'New York' );
	} );
} );

describe( 'serializeRules', () => {
	it( 'joins countries and precise rules', () => {
		expect(
			serializeRules( {
				countries: [ 'CN', 'RU' ],
				precise: [ { country: 'US', level: 'State', value: 'California' } ],
			} )
		).toBe( 'CN,RU,US:State:California' );
	} );

	it( 'upper-cases codes, normalizes the level, and de-dupes', () => {
		expect(
			serializeRules( {
				countries: [ 'us', 'US', 'ca' ],
				precise: [ { country: 'fr', level: 'city', value: 'Paris' } ],
			} )
		).toBe( 'US,CA,FR:City:Paris' );
	} );

	it( 'drops precise rules with no value', () => {
		expect(
			serializeRules( { countries: [], precise: [ { country: 'US', level: 'State', value: '' } ] } )
		).toBe( '' );
	} );

	it( 'defaults to empty', () => {
		expect( serializeRules() ).toBe( '' );
		expect( serializeRules( {} ) ).toBe( '' );
	} );
} );

describe( 'round-trip', () => {
	it( 'parse(serialize(x)) preserves structure', () => {
		const data = {
			countries: [ 'CN', 'RU', 'US' ],
			precise: [
				{ country: 'US', level: 'State', value: 'California' },
				{ country: 'FR', level: 'City', value: 'Paris' },
			],
		};
		expect( parseRules( serializeRules( data ) ) ).toEqual( data );
	} );

	it( 'serialize(parse(x)) preserves a canonical string', () => {
		const str = 'CN,US:State:California,FR:City:Paris';
		expect( serializeRules( parseRules( str ) ) ).toBe( str );
	} );
} );
