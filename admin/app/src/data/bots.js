/**
 * Bundled catalog of well-known bots and crawlers.
 *
 * One row per bot *family*, keyed by a STABLE User-Agent substring token — never
 * a version suffix. The engine (IP_Location_Block::check_ua) matches the token
 * as a plain substring of the request's User-Agent header, so `Googlebot` also
 * matches `Googlebot-Image` / `Googlebot/2.1`, and `Screaming Frog` matches the
 * full `Screaming Frog SEO Spider/x.y` product string.
 *
 * This catalog powers three UX affordances (it does NOT drive the engine):
 *   - the purpose-based preset toggles (see lib/uaPresets.js),
 *   - the "Test a User-Agent" identifier,
 *   - the "detect bots in blocked logs" suggestions.
 *
 * Fields
 *   token        stable UA substring the engine matches on
 *   label        human label (proper nouns; not localized)
 *   category     search | ai | social | seo | feed | archive
 *   purpose      train | search | agent | social | seo | feed
 *   verification 'host' when the operator publishes forward-confirmed reverse
 *                DNS (FCrDNS) for allow-listing, else 'none'
 *   disposition  recommended action in the catalog: 'allow' | 'block'
 *   note         short rationale / source hint
 *
 * Tokens and verification support are sourced from each vendor's crawler docs;
 * see PATTERNS_URL for the aggregated reference list used while curating this.
 */

export const PATTERNS_URL = 'https://iplocationblock.com/docs/bot-catalog/';

export const CATEGORIES = [ 'search', 'ai', 'social', 'seo', 'feed', 'archive' ];
export const PURPOSES = [ 'train', 'search', 'agent', 'social', 'seo', 'feed' ];

export const BOTS = [
	// --- Search engines (support FCrDNS; allow-list with a HOST rule) ---------
	{
		token: 'Googlebot',
		label: 'Googlebot (Google Search)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'Google Search crawler. Verifiable via *.googlebot.com reverse DNS.',
	},
	{
		token: 'bingbot',
		label: 'Bingbot (Microsoft Bing)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'Microsoft Bing crawler. Verifiable via *.search.msn.com reverse DNS.',
	},
	{
		token: 'DuckDuckBot',
		label: 'DuckDuckBot (DuckDuckGo)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'DuckDuckGo crawler. Publishes an allow-list of IPs and reverse DNS.',
	},
	{
		token: 'Applebot',
		label: 'Applebot (Apple / Siri / Spotlight)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'Apple crawler. Verifiable via *.applebot.apple.com reverse DNS.',
	},
	{
		token: 'YandexBot',
		label: 'YandexBot (Yandex)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'Yandex crawler. Verifiable via *.yandex.com/ru/net reverse DNS.',
	},
	{
		token: 'Baiduspider',
		label: 'Baiduspider (Baidu)',
		category: 'search',
		purpose: 'search',
		verification: 'host',
		disposition: 'allow',
		note: 'Baidu crawler. Verifiable via *.baidu.com/.jp reverse DNS.',
	},

	// --- AI training / dataset crawlers (no verification; block to opt out) ---
	{
		token: 'GPTBot',
		label: 'GPTBot (OpenAI)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'OpenAI model-training crawler.',
	},
	{
		token: 'ClaudeBot',
		label: 'ClaudeBot (Anthropic)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'Anthropic model-training crawler.',
	},
	{
		token: 'CCBot',
		label: 'CCBot (Common Crawl)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'Common Crawl; its corpus is a common AI-training dataset source.',
	},
	{
		token: 'Bytespider',
		label: 'Bytespider (ByteDance)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'ByteDance / TikTok data crawler.',
	},
	{
		token: 'meta-externalagent',
		label: 'meta-externalagent (Meta AI)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'Meta AI training crawler (successor to FacebookBot).',
	},
	{
		token: 'Amazonbot',
		label: 'Amazonbot (Amazon)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'Amazon crawler used to improve its assistants.',
	},
	{
		token: 'Google-CloudVertexBot',
		label: 'Google-CloudVertexBot (Vertex AI)',
		category: 'ai',
		purpose: 'train',
		verification: 'none',
		disposition: 'block',
		note: 'Fetches site content for Google Cloud Vertex AI customers.',
	},

	// --- AI search index crawlers (left un-blocked by default for visibility) -
	{
		token: 'OAI-SearchBot',
		label: 'OAI-SearchBot (OpenAI search)',
		category: 'ai',
		purpose: 'search',
		verification: 'none',
		disposition: 'allow',
		note: 'Indexes pages for surfacing in ChatGPT search results.',
	},
	{
		token: 'Claude-SearchBot',
		label: 'Claude-SearchBot (Anthropic search)',
		category: 'ai',
		purpose: 'search',
		verification: 'none',
		disposition: 'allow',
		note: 'Indexes pages for Claude search results.',
	},
	{
		token: 'PerplexityBot',
		label: 'PerplexityBot (Perplexity search)',
		category: 'ai',
		purpose: 'search',
		verification: 'none',
		disposition: 'allow',
		note: 'Indexes pages for Perplexity answers with citations.',
	},

	// --- AI agents: user-triggered fetchers (block to opt out of live reads) --
	{
		token: 'ChatGPT-User',
		label: 'ChatGPT-User (OpenAI agent)',
		category: 'ai',
		purpose: 'agent',
		verification: 'none',
		disposition: 'block',
		note: 'Fetches a page live when a ChatGPT user asks about it.',
	},
	{
		token: 'Claude-User',
		label: 'Claude-User (Anthropic agent)',
		category: 'ai',
		purpose: 'agent',
		verification: 'none',
		disposition: 'block',
		note: 'Fetches a page live when a Claude user asks about it.',
	},
	{
		token: 'Perplexity-User',
		label: 'Perplexity-User (Perplexity agent)',
		category: 'ai',
		purpose: 'agent',
		verification: 'none',
		disposition: 'block',
		note: 'Fetches a page live when a Perplexity user asks about it.',
	},
	{
		token: 'meta-externalfetcher',
		label: 'meta-externalfetcher (Meta agent)',
		category: 'ai',
		purpose: 'agent',
		verification: 'none',
		disposition: 'block',
		note: 'Meta assistant live page fetch on a user request.',
	},

	// --- Social / link-preview bots (allow UA-only; no verification) ----------
	{
		token: 'Twitterbot',
		label: 'Twitterbot (X / Twitter cards)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'Builds link-preview cards. Fetches from cloud IPs, so do NOT geo-verify.',
	},
	{
		token: 'facebookexternalhit',
		label: 'facebookexternalhit (Facebook)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'Facebook / Instagram link-preview scraper.',
	},
	{
		token: 'LinkedInBot',
		label: 'LinkedInBot (LinkedIn)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'LinkedIn link-preview bot.',
	},
	{
		token: 'Slackbot',
		label: 'Slackbot (Slack unfurls)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'Slack link unfurling.',
	},
	{
		token: 'Discordbot',
		label: 'Discordbot (Discord embeds)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'Discord embed generator.',
	},
	{
		token: 'TelegramBot',
		label: 'TelegramBot (Telegram previews)',
		category: 'social',
		purpose: 'social',
		verification: 'none',
		disposition: 'allow',
		note: 'Telegram link-preview bot.',
	},

	// --- SEO / marketing crawlers (block the aggressive commercial ones) ------
	{
		token: 'AhrefsBot',
		label: 'AhrefsBot (Ahrefs)',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'Ahrefs backlink crawler; high-volume commercial SEO index.',
	},
	{
		token: 'SemrushBot',
		label: 'SemrushBot (Semrush)',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'Semrush crawler; high-volume commercial SEO index.',
	},
	{
		token: 'MJ12bot',
		label: 'MJ12bot (Majestic)',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'Majestic backlink crawler.',
	},
	{
		token: 'DotBot',
		label: 'DotBot (Moz)',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'Moz link-index crawler.',
	},
	{
		token: 'DataForSeoBot',
		label: 'DataForSeoBot (DataForSEO)',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'DataForSEO commercial crawler.',
	},
	{
		token: 'Screaming Frog',
		label: 'Screaming Frog SEO Spider',
		category: 'seo',
		purpose: 'seo',
		verification: 'none',
		disposition: 'block',
		note: 'Desktop SEO auditing tool; can be run against any site.',
	},

	// --- Feed readers (allow; also covered by the *:FEED catch-all) -----------
	{
		token: 'Feedly',
		label: 'Feedly (feed reader)',
		category: 'feed',
		purpose: 'feed',
		verification: 'none',
		disposition: 'allow',
		note: 'RSS/Atom reader. The *:FEED rule already permits feed requests.',
	},
	{
		token: 'Inoreader',
		label: 'Inoreader (feed reader)',
		category: 'feed',
		purpose: 'feed',
		verification: 'none',
		disposition: 'allow',
		note: 'RSS/Atom reader.',
	},
	{
		token: 'NewsBlur',
		label: 'NewsBlur (feed reader)',
		category: 'feed',
		purpose: 'feed',
		verification: 'none',
		disposition: 'allow',
		note: 'RSS/Atom reader.',
	},

	// --- Archive crawlers -----------------------------------------------------
	{
		token: 'archive.org_bot',
		label: 'archive.org_bot (Internet Archive)',
		category: 'archive',
		purpose: 'search',
		verification: 'none',
		disposition: 'allow',
		note: 'Wayback Machine crawler. Indexes/archives the public web.',
	},
];

/** Lower-cased token -> bot row, for fast UA identification. */
const BY_TOKEN = BOTS.reduce( ( map, bot ) => {
	map[ bot.token.toLowerCase() ] = bot;
	return map;
}, {} );

/**
 * Identify every catalog bot whose token appears (case-insensitively, as a
 * substring — the same way the engine matches) in a User-Agent string.
 *
 * @param {string} userAgent a raw User-Agent header
 * @return {Array<object>} matching catalog rows (may be empty)
 */
export function botsInUserAgent( userAgent ) {
	const ua = String( userAgent || '' ).toLowerCase();
	if ( ! ua ) {
		return [];
	}
	return BOTS.filter( ( bot ) => ua.includes( bot.token.toLowerCase() ) );
}

/**
 * Look up a single catalog row by its exact token.
 *
 * @param {string} token
 * @return {object|null}
 */
export function botByToken( token ) {
	return BY_TOKEN[ String( token || '' ).toLowerCase() ] || null;
}
