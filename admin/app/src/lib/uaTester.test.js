import { evaluateUa, botCandidatesFromLogs } from './uaTester';
import { parseUaList } from './uaRules';

const GPT_UA = 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)';
const GOOGLE_UA =
	'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/605.1';

describe( 'evaluateUa', () => {
	it( 'blocks a UA that hits a `#*` rule (client-decidable)', () => {
		const rows = parseUaList( 'GPTBot#*,Googlebot:HOST' );
		const out = evaluateUa( GPT_UA, rows );
		expect( out.verdict ).toBe( 'block' );
		expect( out.ruleIndex ).toBe( 0 );
		expect( out.uncertain ).toBe( false );
		expect( out.matches[ 0 ].rule ).toBe( 'GPTBot#*' );
	} );

	it( 'reports a HOST allow-rule as server-dependent, not a client verdict', () => {
		const rows = parseUaList( 'Googlebot:HOST' );
		const out = evaluateUa( GOOGLE_UA, rows );
		expect( out.verdict ).toBe( 'none' );
		expect( out.uncertain ).toBe( true );
		expect( out.matches ).toHaveLength( 1 );
		expect( out.matches[ 0 ].decided ).toBe( false );
		expect( out.matches[ 0 ].note ).toMatch( /reverse DNS/ );
	} );

	it( 'returns "none" with no matches for a plain browser', () => {
		const rows = parseUaList( 'GPTBot#*,Googlebot:HOST' );
		const out = evaluateUa( BROWSER_UA, rows );
		expect( out.verdict ).toBe( 'none' );
		expect( out.matches ).toEqual( [] );
		expect( out.uncertain ).toBe( false );
	} );

	it( 'flags uncertainty when a server-side rule precedes a decided rule', () => {
		// Googlebot:HOST (server-side) then a catch-all block that fires.
		const rows = parseUaList( 'Googlebot:HOST,*#*' );
		const out = evaluateUa( GOOGLE_UA, rows );
		expect( out.verdict ).toBe( 'block' ); // the *#* fires client-side
		expect( out.ruleIndex ).toBe( 1 );
		expect( out.uncertain ).toBe( true ); // HOST rule could preempt server-side
	} );

	it( 'first decided rule wins (order matters)', () => {
		const rows = parseUaList( 'GPTBot:*,GPTBot#*' );
		const out = evaluateUa( GPT_UA, rows );
		expect( out.verdict ).toBe( 'pass' );
		expect( out.ruleIndex ).toBe( 0 );
	} );

	it( 'treats a negated any-rule as a client-decidable no-op', () => {
		const rows = parseUaList( 'GPTBot#!*' );
		const out = evaluateUa( GPT_UA, rows );
		expect( out.verdict ).toBe( 'none' );
		expect( out.matches[ 0 ].note ).toBe( 'never matches' );
		expect( out.uncertain ).toBe( false );
	} );
} );

describe( 'botCandidatesFromLogs', () => {
	it( 'aggregates catalog bots by token, most frequent first', () => {
		const logs = [
			{ userAgent: GPT_UA },
			{ userAgent: GPT_UA },
			{ userAgent: GOOGLE_UA },
			{ userAgent: BROWSER_UA },
			{ userAgent: '' },
			{},
		];
		const out = botCandidatesFromLogs( logs );
		expect( out[ 0 ] ).toMatchObject( { token: 'GPTBot', count: 2 } );
		expect( out.map( ( c ) => c.token ) ).toEqual( [
			'GPTBot',
			'Googlebot',
		] );
		expect( out[ 0 ].sampleUa ).toBe( GPT_UA );
	} );

	it( 'returns [] when nothing matches', () => {
		expect(
			botCandidatesFromLogs( [ { userAgent: BROWSER_UA } ] )
		).toEqual( [] );
		expect( botCandidatesFromLogs( [] ) ).toEqual( [] );
		expect( botCandidatesFromLogs() ).toEqual( [] );
	} );
} );
