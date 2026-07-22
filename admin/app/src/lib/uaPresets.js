/**
 * Purpose-based bot-rule presets, compiled from the bundled catalog
 * (data/bots.js) into engine-ready rows (lib/uaRules.js).
 *
 * Each preset toggles a fixed set of rows in the `ua_list`:
 *   - it is "active" when ALL its rows are present,
 *   - turning it on appends any missing rows (idempotent, de-duplicated),
 *   - turning it off removes exactly its rows.
 *
 * The five `shipped: true` presets are exactly the modern new-install default
 * (their union, in order, IS MODERN_DEFAULT). The two `shipped: false` presets
 * (block AI agents, allow AI-search crawlers) are opt-in.
 *
 * Rule verbs the presets compile to (validated live against check_ua):
 *   ALLOW verified search  ->  <token>:HOST   (verifies only with reverse DNS on)
 *   ALLOW social / feed    ->  <token>:*  /  *:FEED   (UA-only pass, any country)
 *   BLOCK crawlers         ->  <token>#*      (block any country; no verification)
 */

import { BOTS } from '../data/bots';
import {
	parseUaList,
	serializeUaList,
	serializeRow,
	ruleKey,
} from './uaRules';

const rule = ( ua, action, qualType, qualValue = '' ) => ( {
	ua,
	action,
	qualType,
	qualValue,
	negate: false,
} );

const passHost = ( token ) => rule( token, 'pass', 'host' );
const passAny = ( token ) => rule( token, 'pass', 'any' );
const blockAny = ( token ) => rule( token, 'block', 'any' );

export const PRESETS = [
	{
		id: 'verified-search',
		group: 'allow',
		shipped: true,
		rules: [ 'Googlebot', 'bingbot', 'DuckDuckBot', 'Applebot' ].map(
			passHost
		),
	},
	{
		id: 'feed',
		group: 'allow',
		shipped: true,
		// Catch-all: permit any feed request regardless of the UA.
		rules: [ rule( '*', 'pass', 'feed' ) ],
	},
	{
		id: 'social',
		group: 'allow',
		shipped: true,
		rules: [
			'facebookexternalhit',
			'Twitterbot',
			'LinkedInBot',
			'Slackbot',
			'Discordbot',
		].map( passAny ),
	},
	{
		id: 'ai-training',
		group: 'block',
		shipped: true,
		rules: [
			'GPTBot',
			'ClaudeBot',
			'CCBot',
			'Bytespider',
			'meta-externalagent',
		].map( blockAny ),
	},
	{
		id: 'seo',
		group: 'block',
		shipped: true,
		rules: [ 'AhrefsBot', 'SemrushBot', 'MJ12bot' ].map( blockAny ),
	},
	{
		id: 'ai-agents',
		group: 'block',
		shipped: false,
		rules: [
			'ChatGPT-User',
			'Claude-User',
			'Perplexity-User',
			'meta-externalfetcher',
		].map( blockAny ),
	},
	{
		id: 'ai-search',
		group: 'allow',
		shipped: false,
		rules: [ 'OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot' ].map(
			passAny
		),
	},
];

/** The modern new-install default: the ordered union of the shipped presets. */
export const MODERN_DEFAULT = serializeUaList(
	PRESETS.filter( ( p ) => p.shipped ).flatMap( ( p ) => p.rules )
);

/**
 * Recognized legacy default `ua_list` strings (normalized token strings). The
 * migration offer only appears while the stored list still equals one of these,
 * so customized lists are never touched. Kept in sync with the PHP recognizer
 * in src/Settings/Options.php::is_legacy_ua_list().
 */
export const LEGACY_DEFAULTS = [
	// The 3.0.3+ IP Geo Block / IP Location Block default (embed.ly rewritten).
	'Google:HOST,bot:HOST,slurp:HOST,spider:HOST,archive:HOST,*:FEED,embed.ly:HOST,Twitterbot:US,Facebot:US',
	// The pre-3.0.3 form, before embed.ly was rewritten (belt-and-suspenders).
	'Google:HOST,bot:HOST,slurp:HOST,spider:HOST,archive:HOST,*:FEED,*:HOST=embed.ly,Twitterbot:US,Facebot:US',
];

const normalize = ( str ) =>
	String( str || '' )
		.split( /[,\n]/ )
		.map( ( token ) => token.trim() )
		.filter( Boolean )
		.join( ',' );

/**
 * Whether a stored list is one of the recognized stale defaults (never a
 * customized list).
 *
 * @param {string} str stored ua_list
 * @return {boolean}
 */
export function isLegacyDefault( str ) {
	return LEGACY_DEFAULTS.includes( normalize( str ) );
}

/**
 * The set of canonical rule keys a preset contributes.
 *
 * @param {object} preset
 * @return {Set<string>}
 */
function presetKeys( preset ) {
	return new Set( preset.rules.map( ruleKey ) );
}

/**
 * Whether every rule of a preset is already present in the rows.
 *
 * @param {Array<object>} rows
 * @param {object}        preset
 * @return {boolean}
 */
export function presetActive( rows, preset ) {
	const present = new Set( ( rows || [] ).map( ruleKey ) );
	return preset.rules.every( ( r ) => present.has( ruleKey( r ) ) );
}

/**
 * Append a preset's missing rows (idempotent, de-duplicated). Existing rows are
 * preserved in place; new rows are appended in preset order.
 *
 * @param {Array<object>} rows
 * @param {object}        preset
 * @return {Array<object>} next rows
 */
export function applyPreset( rows, preset ) {
	const present = new Set( ( rows || [] ).map( ruleKey ) );
	const additions = preset.rules.filter(
		( r ) => ! present.has( ruleKey( r ) )
	);
	return [ ...( rows || [] ), ...additions.map( ( r ) => ( { ...r } ) ) ];
}

/**
 * Remove exactly a preset's rows from the list.
 *
 * @param {Array<object>} rows
 * @param {object}        preset
 * @return {Array<object>} next rows
 */
export function removePreset( rows, preset ) {
	const keys = presetKeys( preset );
	return ( rows || [] ).filter( ( r ) => ! keys.has( ruleKey( r ) ) );
}

/**
 * Toggle a preset on or off against the current rows.
 *
 * @param {Array<object>} rows
 * @param {object}        preset
 * @param {boolean}       on
 * @return {Array<object>} next rows
 */
export function togglePreset( rows, preset, on ) {
	return on ? applyPreset( rows, preset ) : removePreset( rows, preset );
}

/** All UA tokens referenced by preset bot-rules (excludes the `*` catch-all). */
export const PRESET_TOKENS = [
	...new Set(
		PRESETS.flatMap( ( p ) => p.rules )
			.map( ( r ) => r.ua )
			.filter( ( ua ) => ua !== '*' )
	),
];

/** Catalog tokens, for cross-validating that presets reference real bots. */
export const CATALOG_TOKENS = BOTS.map( ( b ) => b.token );

// Re-export for callers that build the modern default string.
export { serializeRow };
