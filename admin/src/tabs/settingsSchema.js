/**
 * Declarative spec of the settings form, extracted 1:1 from the classic
 * tab-settings.php sections. Drives <Settings> so fields stay in sync with
 * the stored option shape.
 *
 * field.type: select | toggle | bitmask | text | number | textarea
 *   toggle  -> boolean/int 0-1 checkbox
 *   bitmask -> single "Block by location" checkbox writing 1/0 (bit 1)
 * field.advanced: field needs a dynamic data source (providers list, WP
 *   content, discovered slugs) or is an action — rendered as a placeholder
 *   note for now (built in later increments). Skipped: pure display/debug
 *   and action buttons.
 */
import { __ } from '@wordpress/i18n';

const RESPONSE_CODES = [
	'200', '301', '302', '303', '307', '400', '403', '404', '406', '410', '500', '503',
].map( ( v ) => ( { label: v, value: v } ) );

export const SECTIONS = [
	{
		key: 'validation-rule',
		title: __( 'Validation rules and behavior', 'ip-location-block' ),
		fields: [
			{ path: 'matching_rule', label: __( 'Matching rule', 'ip-location-block' ), type: 'select', options: [
				{ label: __( '— select —', 'ip-location-block' ), value: '-1' },
				{ label: __( 'Whitelist', 'ip-location-block' ), value: '0' },
				{ label: __( 'Blacklist', 'ip-location-block' ), value: '1' },
			], help: __( 'How “Block by location” behaves.', 'ip-location-block' ) },
			{ path: 'white_list', label: __( 'Whitelist of country code', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.matching_rule ) === 0, help: __( 'Comma-separated codes. XX=private, ZZ=unknown, YY=non-country.', 'ip-location-block' ) },
			{ path: 'black_list', label: __( 'Blacklist of country code', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.matching_rule ) === 1 },
			{ path: 'use_asn', label: __( 'Use Autonomous System Number (ASN)', 'ip-location-block' ), type: 'toggle' },
			{ path: 'validation.proxy', label: __( '$_SERVER keys for extra IP addresses', 'ip-location-block' ), type: 'text', help: __( 'e.g. HTTP_X_FORWARDED_FOR when behind a proxy.', 'ip-location-block' ) },
			{ path: 'extra_ips.white_list', label: __( 'Whitelist of extra IPs (CIDR, ASN)', 'ip-location-block' ), type: 'textarea' },
			{ path: 'extra_ips.black_list', label: __( 'Blacklist of extra IPs (CIDR, ASN)', 'ip-location-block' ), type: 'textarea' },
			{ path: 'signature', label: __( 'Bad signatures in query', 'ip-location-block' ), type: 'textarea', help: __( 'Comma/newline-separated malicious signatures.', 'ip-location-block' ) },
			{ path: 'validation.mimetype', label: __( 'Prevent malicious file uploading', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '0' },
				{ label: __( 'Verify file extension and MIME type', 'ip-location-block' ), value: '1' },
				{ label: __( 'Verify file extension only', 'ip-location-block' ), value: '2' },
			] },
			{ path: 'mimetype.black_list', label: __( 'Blacklist of forbidden file extensions', 'ip-location-block' ), type: 'text' },
			{ path: 'mimetype.capability', label: __( 'Capabilities to be verified', 'ip-location-block' ), type: 'text' },
			{ path: 'validation.metadata', label: __( 'Metadata Exploit Protection', 'ip-location-block' ), type: 'toggle' },
			{ path: 'response_code', label: __( 'Response code (RFC 2616)', 'ip-location-block' ), type: 'select', options: RESPONSE_CODES },
			{ path: 'redirect_uri', label: __( 'Redirect URL', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.response_code ) < 400 },
			{ path: 'response_msg', label: __( 'Response message', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.response_code ) >= 400 },
			{ path: 'validation.timing', label: __( 'Validation timing', 'ip-location-block' ), type: 'select', options: [
				{ label: __( '“init” action hook', 'ip-location-block' ), value: '0' },
				{ label: __( '“mu-plugins”', 'ip-location-block' ), value: '1' },
			] },
			{ path: 'simulate', label: __( 'Simulation mode (log only, do not block)', 'ip-location-block' ), type: 'toggle' },
		],
	},
	{
		key: 'validation-target',
		title: __( 'Back-end target settings', 'ip-location-block' ),
		fields: [
			{ path: 'validation.comment', label: __( 'Comment post', 'ip-location-block' ), type: 'toggle' },
			{ path: 'validation.xmlrpc', label: __( 'XML-RPC', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '0' },
				{ label: __( 'Block by location', 'ip-location-block' ), value: '1' },
				{ label: __( 'Completely close', 'ip-location-block' ), value: '2' },
			] },
			{ path: 'validation.login', label: __( 'Login form', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_action.login', label: __( 'Target: Log in', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_action.register', label: __( 'Target: Register', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_action.resetpass', label: __( 'Target: Password Reset', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_action.lostpassword', label: __( 'Target: Lost Password', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_action.postpass', label: __( 'Target: Password protected', 'ip-location-block' ), type: 'toggle' },
			{ path: 'login_fails', label: __( 'Max failed login attempts per IP', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '-1' },
				...[ '0', '1', '3', '5', '7', '10' ].map( ( v ) => ( { label: v, value: v } ) ),
			] },
			{ path: 'validation.admin', label: __( 'Admin area — block by location', 'ip-location-block' ), type: 'bitmask' },
			{ path: 'validation.ajax', label: __( 'Admin ajax/post — block by location', 'ip-location-block' ), type: 'bitmask' },
			{ path: 'validation.plugins', label: __( 'Plugins area', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '0' },
				{ label: __( 'Block by location', 'ip-location-block' ), value: '1' },
			] },
			{ path: 'validation.themes', label: __( 'Themes area', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '0' },
				{ label: __( 'Block by location', 'ip-location-block' ), value: '1' },
			] },
			{ path: 'exception.admin', label: __( 'Admin ajax/post exceptions', 'ip-location-block' ), type: 'text', advanced: true },
			{ path: 'exception.plugins', label: __( 'Plugins area exceptions', 'ip-location-block' ), type: 'text', advanced: true },
			{ path: 'exception.themes', label: __( 'Themes area exceptions', 'ip-location-block' ), type: 'text', advanced: true },
		],
	},
	{
		key: 'public',
		title: __( 'Front-end target settings', 'ip-location-block' ),
		fields: [
			{ path: 'validation.public', label: __( 'Public facing pages — block by location', 'ip-location-block' ), type: 'toggle' },
			{ path: 'public.cache_bypass', label: __( 'Bypass page cache', 'ip-location-block' ), type: 'toggle', help: __( 'Disable full-page caching so every visitor is geo-checked.', 'ip-location-block' ) },
			{ path: 'public.matching_rule', label: __( 'Matching rule', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Follow “Validation rules”', 'ip-location-block' ), value: '-1' },
				{ label: __( 'Whitelist', 'ip-location-block' ), value: '0' },
				{ label: __( 'Blacklist', 'ip-location-block' ), value: '1' },
			] },
			{ path: 'public.white_list', label: __( 'Whitelist of country code', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.public?.matching_rule ) === 0 },
			{ path: 'public.black_list', label: __( 'Blacklist of country code', 'ip-location-block' ), type: 'text', showIf: ( s ) => Number( s.public?.matching_rule ) === 1 },
			{ path: 'public.response_code', label: __( 'Response code (RFC 2616)', 'ip-location-block' ), type: 'select', options: RESPONSE_CODES },
			{ path: 'public.redirect_uri', label: __( 'Redirect URL', 'ip-location-block' ), type: 'text' },
			{ path: 'public.response_msg', label: __( 'Response message', 'ip-location-block' ), type: 'text' },
			{ path: 'public.target_rule', label: __( 'Validation target', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'All requests', 'ip-location-block' ), value: '0' },
				{ label: __( 'Specify the targets', 'ip-location-block' ), value: '1' },
			] },
			{ path: 'public.target_pages', label: __( 'Target pages / posts / categories / tags', 'ip-location-block' ), type: 'text', advanced: true },
			{ path: 'public.behavior', label: __( 'Block badly-behaved bots and crawlers', 'ip-location-block' ), type: 'toggle' },
			{ path: 'behavior.view', label: __( 'Condition — page views', 'ip-location-block' ), type: 'number' },
			{ path: 'behavior.time', label: __( 'Condition — seconds', 'ip-location-block' ), type: 'number' },
			{ path: 'public.ua_list', label: __( 'UA string and qualification', 'ip-location-block' ), type: 'textarea' },
			{ path: 'public.dnslkup', label: __( 'Reverse DNS lookup', 'ip-location-block' ), type: 'toggle' },
		],
	},
	{
		key: 'recording',
		title: __( 'Privacy and record settings', 'ip-location-block' ),
		fields: [
			{ path: 'anonymize', label: __( 'Anonymize IP address', 'ip-location-block' ), type: 'toggle' },
			{ path: 'restrict_api', label: __( 'Do not send IP address to external APIs', 'ip-location-block' ), type: 'toggle' },
			{ path: 'cache_hold', label: __( 'Record “IP address cache”', 'ip-location-block' ), type: 'toggle' },
			{ path: 'cache_time', label: __( 'Cache expiration time [sec]', 'ip-location-block' ), type: 'number' },
			{ path: 'validation.reclogs', label: __( 'Record “Validation logs”', 'ip-location-block' ), type: 'select', options: [
				{ label: __( 'Disable', 'ip-location-block' ), value: '0' },
				{ label: __( 'When blocked', 'ip-location-block' ), value: '1' },
				{ label: __( 'When passed', 'ip-location-block' ), value: '2' },
				{ label: __( 'Blocked or passed (not whitelisted)', 'ip-location-block' ), value: '6' },
				{ label: __( 'Unauthenticated visitor', 'ip-location-block' ), value: '3' },
				{ label: __( 'Authenticated user', 'ip-location-block' ), value: '4' },
				{ label: __( 'All the validation', 'ip-location-block' ), value: '5' },
			] },
			{ path: 'validation.explogs', label: __( 'Logs expiration [days]', 'ip-location-block' ), type: 'number' },
			{ path: 'validation.postkey', label: __( '$_POST keys to record', 'ip-location-block' ), type: 'text' },
			{ path: 'cache_time_gc', label: __( 'GC interval [sec]', 'ip-location-block' ), type: 'number' },
			{ path: 'save_statistics', label: __( 'Record “Statistics of validation”', 'ip-location-block' ), type: 'toggle' },
			{ path: 'clean_uninstall', label: __( 'Remove all settings and records at uninstall', 'ip-location-block' ), type: 'toggle' },
		],
	},
	{
		key: 'provider',
		title: __( 'Geolocation API settings', 'ip-location-block' ),
		fields: [
			{ path: 'providers', label: __( 'API selection and key settings', 'ip-location-block' ), type: 'text', advanced: true },
		],
	},
	{
		key: 'database',
		title: __( 'Local database settings', 'ip-location-block' ),
		fields: [
			{ path: 'update.auto', label: __( 'Auto updating (once a month)', 'ip-location-block' ), type: 'toggle' },
			{ path: 'IP2Location.ipv4_path', label: __( 'Database file paths', 'ip-location-block' ), type: 'text', advanced: true },
		],
	},
	{
		key: 'others',
		title: __( 'Plugin settings', 'ip-location-block' ),
		fields: [
			{ path: 'network_wide', label: __( 'Network wide settings', 'ip-location-block' ), type: 'toggle' },
		],
	},
];
