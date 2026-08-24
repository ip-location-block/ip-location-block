import { buildLogsCsv, filterLogRows } from './logsLogic';

const rows = [
	{
		id: 1,
		ip: '192.0.2.1',
		code: 'US',
		city: 'New York',
		verdict: 'passed',
		listContext: 'whitelist',
		result: 'passed',
		headers: 'X-Test: one',
	},
	{
		id: 2,
		ip: '198.51.100.2',
		code: 'DE',
		city: 'Berlin',
		verdict: 'blocked',
		listContext: 'blacklist',
		result: 'blocked',
		headers: 'X-Test: two',
	},
];

describe( 'filterLogRows', () => {
	it( 'searches detail fields that are not primary columns', () => {
		expect(
			filterLogRows( rows, 'new york' ).map( ( row ) => row.id )
		).toEqual( [ 1 ] );
	} );

	it( 'applies stable result/list presets', () => {
		expect(
			filterLogRows( rows, '', 'blocked-blacklist' ).map(
				( row ) => row.id
			)
		).toEqual( [ 2 ] );
		expect( filterLogRows( rows, '', 'passed-none' ) ).toEqual( [] );
	} );
} );

describe( 'buildLogsCsv', () => {
	it( 'includes detail columns and escapes quotes/newlines', () => {
		const csv = buildLogsCsv( [
			{
				...rows[ 0 ],
				timeText: '2026-07-17 10:00:00',
				postData: 'message="hello"\nnext',
			},
		] );
		expect( csv ).toContain( '"HTTP headers"' );
		expect( csv ).toContain( '"$_POST data"' );
		expect( csv ).toContain( '"message=""hello""\nnext"' );
	} );
} );
