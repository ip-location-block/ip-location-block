import {
	BOTS,
	CATEGORIES,
	PURPOSES,
	PATTERNS_URL,
	botsInUserAgent,
	botByToken,
} from './bots';

describe( 'BOTS catalog integrity', () => {
	it( 'has a source/reference URL', () => {
		expect( typeof PATTERNS_URL ).toBe( 'string' );
		expect( PATTERNS_URL ).toMatch( /^https?:\/\// );
	} );

	it( 'uses unique tokens (case-insensitively)', () => {
		const seen = new Set();
		BOTS.forEach( ( bot ) => {
			const key = bot.token.toLowerCase();
			expect( seen.has( key ) ).toBe( false );
			seen.add( key );
		} );
		expect( seen.size ).toBe( BOTS.length );
	} );

	it( 'every row carries a non-empty stable token and label', () => {
		BOTS.forEach( ( bot ) => {
			expect( typeof bot.token ).toBe( 'string' );
			expect( bot.token.trim() ).not.toBe( '' );
			// Stable tokens never carry a version suffix.
			expect( bot.token ).not.toMatch( /\/\d/ );
			expect( typeof bot.label ).toBe( 'string' );
			expect( bot.label.trim() ).not.toBe( '' );
		} );
	} );

	it( 'every row has a valid category, purpose and disposition', () => {
		BOTS.forEach( ( bot ) => {
			expect( CATEGORIES ).toContain( bot.category );
			expect( PURPOSES ).toContain( bot.purpose );
			expect( [ 'allow', 'block' ] ).toContain( bot.disposition );
			expect( [ 'host', 'none' ] ).toContain( bot.verification );
		} );
	} );

	it( 'only search-type bots claim reverse-DNS verification', () => {
		BOTS.filter( ( b ) => b.verification === 'host' ).forEach( ( bot ) => {
			expect( bot.category ).toBe( 'search' );
		} );
	} );

	it( 'covers every category at least once', () => {
		CATEGORIES.forEach( ( category ) => {
			expect( BOTS.some( ( b ) => b.category === category ) ).toBe( true );
		} );
	} );

	it( 'includes the key AI-crawler families the 2016 defaults lacked', () => {
		const tokens = BOTS.map( ( b ) => b.token );
		[
			'GPTBot',
			'ClaudeBot',
			'CCBot',
			'Bytespider',
			'meta-externalagent',
			'PerplexityBot',
		].forEach( ( token ) => expect( tokens ).toContain( token ) );
	} );
} );

describe( 'botsInUserAgent', () => {
	it( 'matches a token as a case-insensitive substring (as the engine does)', () => {
		const hits = botsInUserAgent(
			'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)'
		);
		expect( hits.map( ( b ) => b.token ) ).toContain( 'GPTBot' );
	} );

	it( 'matches Googlebot inside a versioned product string', () => {
		const hits = botsInUserAgent(
			'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
		);
		expect( hits.map( ( b ) => b.token ) ).toEqual( [ 'Googlebot' ] );
	} );

	it( 'matches the multi-word Screaming Frog product token', () => {
		const hits = botsInUserAgent( 'Screaming Frog SEO Spider/19.0' );
		expect( hits.map( ( b ) => b.token ) ).toContain( 'Screaming Frog' );
	} );

	it( 'returns [] for a plain browser or empty input', () => {
		expect(
			botsInUserAgent( 'Mozilla/5.0 (Macintosh) Safari/605' )
		).toEqual( [] );
		expect( botsInUserAgent( '' ) ).toEqual( [] );
		expect( botsInUserAgent( null ) ).toEqual( [] );
	} );
} );

describe( 'botByToken', () => {
	it( 'resolves a row case-insensitively', () => {
		expect( botByToken( 'gptbot' )?.token ).toBe( 'GPTBot' );
		expect( botByToken( 'GPTBot' )?.label ).toMatch( /OpenAI/ );
	} );

	it( 'returns null for an unknown token', () => {
		expect( botByToken( 'nope' ) ).toBeNull();
		expect( botByToken( '' ) ).toBeNull();
	} );
} );
