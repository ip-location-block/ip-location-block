import {
	PRESETS,
	MODERN_DEFAULT,
	LEGACY_DEFAULTS,
	PRESET_TOKENS,
	CATALOG_TOKENS,
	isLegacyDefault,
	presetActive,
	applyPreset,
	removePreset,
	togglePreset,
} from './uaPresets';
import { parseUaList, serializeUaList } from './uaRules';

describe( 'PRESETS integrity', () => {
	it( 'has unique ids', () => {
		const ids = PRESETS.map( ( p ) => p.id );
		expect( new Set( ids ).size ).toBe( ids.length );
	} );

	it( 'every preset bot-rule token exists in the bundled catalog', () => {
		PRESET_TOKENS.forEach( ( token ) => {
			expect( CATALOG_TOKENS ).toContain( token );
		} );
	} );

	it( 'block presets compile to `#*` and allow presets to `:` rules', () => {
		PRESETS.forEach( ( preset ) => {
			preset.rules.forEach( ( r ) => {
				expect( r.action ).toBe(
					preset.group === 'block' ? 'block' : 'pass'
				);
			} );
		} );
	} );
} );

describe( 'MODERN_DEFAULT', () => {
	it( 'is the ordered union of the shipped presets', () => {
		const expected = serializeUaList(
			PRESETS.filter( ( p ) => p.shipped ).flatMap( ( p ) => p.rules )
		);
		expect( MODERN_DEFAULT ).toBe( expected );
	} );

	it( 'matches the exact string pinned against the live engine', () => {
		expect( MODERN_DEFAULT ).toBe(
			'Googlebot:HOST,bingbot:HOST,DuckDuckBot:HOST,Applebot:HOST,' +
				'*:FEED,facebookexternalhit:*,Twitterbot:*,LinkedInBot:*,' +
				'Slackbot:*,Discordbot:*,GPTBot#*,ClaudeBot#*,CCBot#*,' +
				'Bytespider#*,meta-externalagent#*,AhrefsBot#*,SemrushBot#*,MJ12bot#*'
		);
	} );

	it( 'drops the stale 2016 UA tokens (broad substrings & dead bots)', () => {
		const uas = parseUaList( MODERN_DEFAULT ).map( ( r ) => r.ua );
		// The over-broad and dead 2016 tokens must not appear as rule UAs.
		[ 'Google', 'bot', 'spider', 'slurp', 'archive', 'Facebot', 'embed.ly' ].forEach(
			( stale ) => expect( uas ).not.toContain( stale )
		);
	} );

	it( 'shows all five shipped presets as active on a fresh default', () => {
		const rows = parseUaList( MODERN_DEFAULT );
		PRESETS.filter( ( p ) => p.shipped ).forEach( ( preset ) => {
			expect( presetActive( rows, preset ) ).toBe( true );
		} );
	} );

	it( 'shows the opt-in presets as inactive on a fresh default', () => {
		const rows = parseUaList( MODERN_DEFAULT );
		PRESETS.filter( ( p ) => ! p.shipped ).forEach( ( preset ) => {
			expect( presetActive( rows, preset ) ).toBe( false );
		} );
	} );
} );

describe( 'preset toggle add / remove / idempotency', () => {
	const search = PRESETS.find( ( p ) => p.id === 'verified-search' );
	const agents = PRESETS.find( ( p ) => p.id === 'ai-agents' );

	it( 'applyPreset adds all rows and marks the preset active', () => {
		const rows = applyPreset( [], agents );
		expect( presetActive( rows, agents ) ).toBe( true );
		expect( serializeUaList( rows ) ).toBe(
			'ChatGPT-User#*,Claude-User#*,Perplexity-User#*,meta-externalfetcher#*'
		);
	} );

	it( 'applyPreset is idempotent (no duplicate rows)', () => {
		const once = applyPreset( [], search );
		const twice = applyPreset( once, search );
		expect( serializeUaList( twice ) ).toBe( serializeUaList( once ) );
	} );

	it( 'de-dupes against an equivalent existing row (case-insensitive qual)', () => {
		const rows = parseUaList( 'Googlebot:host' ); // lower-case HOST
		const next = applyPreset( rows, search );
		// Googlebot already present -> only the 3 remaining allow-rows are added.
		expect( next ).toHaveLength( 4 );
		expect( presetActive( next, search ) ).toBe( true );
	} );

	it( 'removePreset removes exactly its rows and leaves others intact', () => {
		const rows = parseUaList( 'GPTBot#*,' + MODERN_DEFAULT );
		const next = removePreset( rows, search );
		expect( presetActive( next, search ) ).toBe( false );
		// A non-preset row survives.
		expect( serializeUaList( next ) ).toContain( 'GPTBot#*' );
		// Other shipped presets remain.
		expect( serializeUaList( next ) ).toContain( '*:FEED' );
	} );

	it( 'togglePreset off then on round-trips the rule set', () => {
		const rows = parseUaList( MODERN_DEFAULT );
		const off = togglePreset( rows, search, false );
		const on = togglePreset( off, search, true );
		expect( presetActive( on, search ) ).toBe( true );
	} );
} );

describe( 'isLegacyDefault', () => {
	it( 'recognizes the stored legacy default (newlines == commas)', () => {
		expect(
			isLegacyDefault(
				'Google:HOST,bot:HOST,slurp:HOST\nspider:HOST,archive:HOST,*:FEED\nembed.ly:HOST,Twitterbot:US,Facebot:US'
			)
		).toBe( true );
	} );

	it( 'recognizes each catalogued legacy form', () => {
		LEGACY_DEFAULTS.forEach( ( legacy ) =>
			expect( isLegacyDefault( legacy ) ).toBe( true )
		);
	} );

	it( 'does NOT match a customized or modern list', () => {
		expect( isLegacyDefault( MODERN_DEFAULT ) ).toBe( false );
		expect( isLegacyDefault( 'Google:HOST,bot:HOST' ) ).toBe( false );
		expect( isLegacyDefault( '' ) ).toBe( false );
	} );
} );
