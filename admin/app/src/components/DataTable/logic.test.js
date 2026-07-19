/**
 * Unit tests for the pure <DataTable> logic.
 */
import {
	compareValues,
	sortRows,
	filterRows,
	paginate,
	pageCount,
	clampPage,
	highlightSegments,
	nextSort,
} from './logic';

const rows = [
	{ id: 1, ip: '10.0.0.2', code: 'US', hits: 5 },
	{ id: 2, ip: '10.0.0.10', code: 'de', hits: 12 },
	{ id: 3, ip: '10.0.0.1', code: 'US', hits: 5 },
	{ id: 4, ip: '10.0.0.3', code: null, hits: 1 },
];

describe( 'compareValues', () => {
	it( 'compares numbers numerically', () => {
		expect( compareValues( 2, 10 ) ).toBeLessThan( 0 );
		expect( compareValues( 10, 2 ) ).toBeGreaterThan( 0 );
		expect( compareValues( 5, 5 ) ).toBe( 0 );
	} );

	it( 'treats numeric strings as numbers (natural order)', () => {
		expect( compareValues( '2', '10' ) ).toBeLessThan( 0 );
	} );

	it( 'sorts empty/null first', () => {
		expect( compareValues( null, 'US' ) ).toBeLessThan( 0 );
		expect( compareValues( 'US', '' ) ).toBeGreaterThan( 0 );
		expect( compareValues( null, undefined ) ).toBe( 0 );
	} );

	it( 'is case-insensitive for strings', () => {
		expect( compareValues( 'de', 'DE' ) ).toBe( 0 );
	} );
} );

describe( 'sortRows', () => {
	it( 'sorts ascending and does not mutate input', () => {
		const out = sortRows( rows, 'ip', 'asc' );
		expect( out.map( ( r ) => r.id ) ).toEqual( [ 3, 1, 4, 2 ] ); // natural: .1 .2 .3 .10
		expect( rows[ 0 ].id ).toBe( 1 ); // original untouched
	} );

	it( 'sorts descending', () => {
		const out = sortRows( rows, 'hits', 'desc' );
		expect( out.map( ( r ) => r.id ) ).toEqual( [ 2, 1, 3, 4 ] );
	} );

	it( 'is stable on ties (keeps original order)', () => {
		const out = sortRows( rows, 'hits', 'asc' );
		// hits: 1(id4), 5(id1), 5(id3), 12(id2) — id1 before id3 (original order)
		expect( out.map( ( r ) => r.id ) ).toEqual( [ 4, 1, 3, 2 ] );
	} );

	it( 'returns a copy unsorted when dir is none', () => {
		const out = sortRows( rows, 'ip', 'none' );
		expect( out ).toEqual( rows );
		expect( out ).not.toBe( rows );
	} );
} );

describe( 'filterRows', () => {
	it( 'matches case-insensitively across keys', () => {
		expect( filterRows( rows, 'us', [ 'code' ] ).map( ( r ) => r.id ) ).toEqual( [ 1, 3 ] );
	} );

	it( 'searches all values when no keys given', () => {
		expect( filterRows( rows, '10.0.0.10' ).map( ( r ) => r.id ) ).toEqual( [ 2 ] );
	} );

	it( 'ignores null values safely', () => {
		expect( () => filterRows( rows, 'x', [ 'code' ] ) ).not.toThrow();
	} );

	it( 'empty query returns all', () => {
		expect( filterRows( rows, '   ' ) ).toHaveLength( 4 );
	} );
} );

describe( 'pagination', () => {
	it( 'pageCount rounds up and is at least 1', () => {
		expect( pageCount( 0, 25 ) ).toBe( 1 );
		expect( pageCount( 26, 25 ) ).toBe( 2 );
		expect( pageCount( 50, 0 ) ).toBe( 1 );
	} );

	it( 'clampPage keeps page in range', () => {
		expect( clampPage( 0, 100, 25 ) ).toBe( 1 );
		expect( clampPage( 99, 100, 25 ) ).toBe( 4 );
		expect( clampPage( 2, 100, 25 ) ).toBe( 2 );
	} );

	it( 'paginate slices the right page', () => {
		expect( paginate( rows, 1, 2 ).map( ( r ) => r.id ) ).toEqual( [ 1, 2 ] );
		expect( paginate( rows, 2, 2 ).map( ( r ) => r.id ) ).toEqual( [ 3, 4 ] );
	} );

	it( 'paginate clamps out-of-range page to the last page', () => {
		expect( paginate( rows, 99, 2 ).map( ( r ) => r.id ) ).toEqual( [ 3, 4 ] );
	} );

	it( 'paginate with pageSize<=0 returns all', () => {
		expect( paginate( rows, 1, 0 ) ).toHaveLength( 4 );
	} );
} );

describe( 'highlightSegments', () => {
	it( 'flags every match', () => {
		expect( highlightSegments( 'aXaXa', 'x' ) ).toEqual( [
			{ text: 'a', match: false },
			{ text: 'X', match: true },
			{ text: 'a', match: false },
			{ text: 'X', match: true },
			{ text: 'a', match: false },
		] );
	} );

	it( 'no query returns a single non-match segment', () => {
		expect( highlightSegments( 'abc', '' ) ).toEqual( [ { text: 'abc', match: false } ] );
	} );

	it( 'handles null text', () => {
		expect( highlightSegments( null, 'x' ) ).toEqual( [ { text: '', match: false } ] );
	} );

	it( 'match at start and end', () => {
		expect( highlightSegments( 'xy', 'x' ) ).toEqual( [
			{ text: 'x', match: true },
			{ text: 'y', match: false },
		] );
	} );
} );

describe( 'nextSort', () => {
	it( 'cycles asc -> desc -> none on the same column', () => {
		expect( nextSort( null, 'none', 'ip' ) ).toEqual( { sortKey: 'ip', sortDir: 'asc' } );
		expect( nextSort( 'ip', 'asc', 'ip' ) ).toEqual( { sortKey: 'ip', sortDir: 'desc' } );
		expect( nextSort( 'ip', 'desc', 'ip' ) ).toEqual( { sortKey: null, sortDir: 'none' } );
	} );

	it( 'starts at asc when switching columns', () => {
		expect( nextSort( 'ip', 'desc', 'code' ) ).toEqual( { sortKey: 'code', sortDir: 'asc' } );
	} );
} );
