/**
 * Declarative spec of the settings form, extracted 1:1 from the classic
 * tab-settings.php sections. Drives <Settings> so fields stay in sync with
 * the stored option shape.
 *
 * field.type: select | toggle | bitmask | text | number | textarea plus
 *   purpose-built parity controls for providers, databases, MIME types,
 *   exceptions, and operational settings actions.
 *   toggle  -> boolean/int 0-1 checkbox
 *   bitmask -> single "Block by location" checkbox writing 1/0 (bit 1)
 * Conditional classic debug fields are exposed only when
 * IP_LOCATION_BLOCK_DEBUG is enabled by the host site.
 */
import { __ } from '@wordpress/i18n';

const RESPONSE_CODES = [
	'200',
	'301',
	'302',
	'303',
	'307',
	'400',
	'403',
	'404',
	'406',
	'410',
	'500',
	'503',
].map( ( v ) => ( { label: v, value: v } ) );

export const SECTIONS = [
	{
		key: 'validation-rule',
		title: __( 'Validation rules and behavior', 'ip-location-block' ),
		groups: [
			{
				key: 'location-matching',
				title: __( 'Location matching', 'ip-location-block' ),
				action: 'scan-country',
				fields: [
					{
						path: 'matching_rule',
						label: __( 'Matching rule', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __( '— select —', 'ip-location-block' ),
								value: '-1',
							},
							{
								label: __( 'Whitelist', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __( 'Blacklist', 'ip-location-block' ),
								value: '1',
							},
						],
						tip: __(
							'Whitelist passes only listed countries; blacklist blocks listed countries.',
							'ip-location-block'
						),
						optionDesc: {
							'-1': __(
								'Country blocking is off until you pick whitelist or blacklist.',
								'ip-location-block'
							),
							0: __(
								'Whitelist: block everyone whose country is NOT in the list.',
								'ip-location-block'
							),
							1: __(
								'Blacklist: block everyone whose country IS in the list.',
								'ip-location-block'
							),
						},
					},
					{
						path: 'white_list',
						label: __(
							'Whitelist of country code',
							'ip-location-block'
						),
						type: 'text',
						showIf: ( s ) => Number( s.matching_rule ) === 0,
						help: __(
							'Comma-separated codes. XX=private, ZZ=unknown, YY=non-country. City/state rules: CC:State:Name or CC:City:Name (Region is an alias of State); use ~ for OR, e.g. FR:City:Paris~Montpellier. Names must match the provider exactly — verify on the Search tab.',
							'ip-location-block'
						),
					},
					{
						path: 'black_list',
						label: __(
							'Blacklist of country code',
							'ip-location-block'
						),
						type: 'text',
						showIf: ( s ) => Number( s.matching_rule ) === 1,
						help: __(
							'Comma-separated codes. Also accepts CC:State:Name / CC:City:Name (Region aliases State) and ~ for OR, e.g. US:City:Seattle~Tacoma. Names must match the provider exactly.',
							'ip-location-block'
						),
					},
					{
						path: 'use_asn',
						label: __(
							'Use Autonomous System Number (ASN)',
							'ip-location-block'
						),
						type: 'toggle',
						tip: __(
							'Also match by AS number. Make sure only ASN-capable providers are enabled.',
							'ip-location-block'
						),
					},
				],
			},
			{
				key: 'proxy-ip-overrides',
				title: __( 'Proxy and IP overrides', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.proxy',
						label: __(
							'$_SERVER keys for extra IP addresses',
							'ip-location-block'
						),
						type: 'text',
						help: __(
							'e.g. HTTP_X_FORWARDED_FOR when behind a proxy.',
							'ip-location-block'
						),
					},
					{
						path: 'extra_ips.white_list',
						label: __(
							'Whitelist of extra IPs (CIDR, ASN)',
							'ip-location-block'
						),
						type: 'textarea',
						tool: 'cidr',
						help: __(
							'Comma or newline separated. Use the CIDR calculator to convert a range.',
							'ip-location-block'
						),
					},
					{
						path: 'extra_ips.black_list',
						label: __(
							'Blacklist of extra IPs (CIDR, ASN)',
							'ip-location-block'
						),
						type: 'textarea',
						tool: 'cidr',
						help: __(
							'Comma or newline separated. Use the CIDR calculator to convert a range.',
							'ip-location-block'
						),
					},
				],
			},
			{
				key: 'request-protection',
				title: __( 'Request protection', 'ip-location-block' ),
				fields: [
					{
						path: 'signature',
						label: __(
							'Bad signatures in query',
							'ip-location-block'
						),
						type: 'signature',
						help: __(
							'Comma/newline-separated malicious signatures.',
							'ip-location-block'
						),
					},
					{
						path: 'validation.mimetype',
						label: __(
							'Prevent malicious file uploading',
							'ip-location-block'
						),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __(
									'Verify file extension and MIME type',
									'ip-location-block'
								),
								value: '1',
							},
							{
								label: __(
									'Verify file extension only',
									'ip-location-block'
								),
								value: '2',
							},
						],
					},
					{
						path: 'mimetype.white_list',
						label: __(
							'Whitelist of allowed MIME types',
							'ip-location-block'
						),
						type: 'mime-list',
						showIf: ( s ) => Number( s.validation?.mimetype ) === 1,
					},
					{
						path: 'mimetype.black_list',
						label: __(
							'Blacklist of forbidden file extensions',
							'ip-location-block'
						),
						type: 'text',
						showIf: ( s ) => Number( s.validation?.mimetype ) === 2,
					},
					{
						path: 'mimetype.capability',
						label: __(
							'Capabilities to be verified',
							'ip-location-block'
						),
						type: 'csv',
						showIf: ( s ) => Number( s.validation?.mimetype ) > 0,
					},
					{
						path: 'validation.metadata',
						label: __(
							'Metadata Exploit Protection',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'metadata.pre_update_option',
						label: __(
							'Protected single-site option names',
							'ip-location-block'
						),
						type: 'csv',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
					{
						path: 'metadata.pre_update_site_option',
						label: __(
							'Protected network option names',
							'ip-location-block'
						),
						type: 'csv',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
				],
			},
			{
				key: 'blocked-response',
				title: __( 'Blocked response', 'ip-location-block' ),
				fields: [
					{
						path: 'response_code',
						label: __(
							'Response code (RFC 2616)',
							'ip-location-block'
						),
						type: 'select',
						options: RESPONSE_CODES,
					},
					{
						path: 'redirect_uri',
						label: __( 'Redirect URL', 'ip-location-block' ),
						type: 'text',
						showIf: ( s ) => Number( s.response_code ) < 400,
					},
					{
						path: 'response_msg',
						label: __( 'Response message', 'ip-location-block' ),
						type: 'text',
						showIf: ( s ) => Number( s.response_code ) >= 400,
					},
				],
			},
			{
				key: 'runtime-behavior',
				title: __( 'Runtime behavior', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.timing',
						label: __( 'Validation timing', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __(
									'“init” action hook',
									'ip-location-block'
								),
								value: '0',
							},
							{
								label: __(
									'“mu-plugins”',
									'ip-location-block'
								),
								value: '1',
							},
						],
						tip: __(
							'When the geolocation check runs during the request lifecycle.',
							'ip-location-block'
						),
						optionDesc: {
							0: __(
								'Runs on the “init” hook — compatible with most setups.',
								'ip-location-block'
							),
							1: __(
								'Runs earlier as a mu-plugin — blocks before other plugins load, but is more invasive.',
								'ip-location-block'
							),
						},
					},
					{
						path: 'simulate',
						label: __(
							'Simulation mode (log only, do not block)',
							'ip-location-block'
						),
						type: 'toggle',
						tip: __(
							'Records what would be blocked without actually blocking — use to test rules safely.',
							'ip-location-block'
						),
					},
				],
			},
		],
	},
	{
		key: 'validation-target',
		title: __( 'Back-end target settings', 'ip-location-block' ),
		groups: [
			{
				key: 'entry-points',
				title: __( 'Entry points', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.comment',
						label: __( 'Comment post', 'ip-location-block' ),
						type: 'toggle',
					},
					{
						path: 'comment.pos',
						label: __(
							'Message on comment form',
							'ip-location-block'
						),
						type: 'select',
						options: [
							{
								label: __( 'None', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __( 'Top', 'ip-location-block' ),
								value: '1',
							},
							{
								label: __( 'Bottom', 'ip-location-block' ),
								value: '2',
							},
						],
					},
					{
						path: 'comment.msg',
						label: __(
							'Comment-form message text',
							'ip-location-block'
						),
						type: 'textarea',
						showIf: ( s ) => Number( s.comment?.pos ) > 0,
						help: __(
							'Basic safe HTML is allowed.',
							'ip-location-block'
						),
					},
					{
						path: 'validation.xmlrpc',
						label: __( 'XML-RPC', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __(
									'Block by location',
									'ip-location-block'
								),
								value: '1',
							},
							{
								label: __(
									'Completely close',
									'ip-location-block'
								),
								value: '2',
							},
						],
					},
				],
			},
			{
				key: 'authentication',
				title: __( 'Authentication', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.login',
						label: __( 'Login form', 'ip-location-block' ),
						type: 'toggle',
					},
					{
						path: 'login_action.login',
						label: __( 'Target: Log in', 'ip-location-block' ),
						type: 'toggle',
					},
					{
						path: 'login_action.register',
						label: __( 'Target: Register', 'ip-location-block' ),
						type: 'toggle',
					},
					{
						path: 'login_action.resetpass',
						label: __(
							'Target: Password Reset',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'login_action.lostpassword',
						label: __(
							'Target: Lost Password',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'login_action.postpass',
						label: __(
							'Target: Password protected',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'login_fails',
						label: __(
							'Max failed login attempts per IP',
							'ip-location-block'
						),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '-1',
							},
							...[ '0', '1', '3', '5', '7', '10' ].map(
								( v ) => ( {
									label: v,
									value: v,
								} )
							),
						],
					},
				],
			},
			{
				key: 'admin-assets',
				title: __( 'Admin and assets', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.admin',
						label: __(
							'Admin area — block by location',
							'ip-location-block'
						),
						type: 'bitmask',
					},
					{
						path: 'validation.ajax',
						label: __(
							'Admin ajax/post — block by location',
							'ip-location-block'
						),
						type: 'bitmask',
					},
					{
						path: 'validation.plugins',
						label: __( 'Plugins area', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __(
									'Block by location',
									'ip-location-block'
								),
								value: '1',
							},
						],
					},
					{
						path: 'validation.themes',
						label: __( 'Themes area', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __(
									'Block by location',
									'ip-location-block'
								),
								value: '1',
							},
						],
					},
				],
			},
			{
				key: 'exceptions',
				title: __( 'Exceptions', 'ip-location-block' ),
				fields: [
					{
						path: 'exception.admin',
						label: __(
							'Admin ajax/post exceptions',
							'ip-location-block'
						),
						type: 'exception-editor',
						target: 'admin',
						source: 'exceptions.admin',
					},
					{
						path: 'exception.plugins',
						label: __(
							'Plugins area exceptions',
							'ip-location-block'
						),
						type: 'exception-editor',
						target: 'plugins',
						source: 'exceptions.plugins',
					},
					{
						path: 'exception.themes',
						label: __(
							'Themes area exceptions',
							'ip-location-block'
						),
						type: 'exception-editor',
						target: 'themes',
						source: 'exceptions.themes',
					},
				],
			},
		],
	},
	{
		key: 'public',
		title: __( 'Front-end target settings', 'ip-location-block' ),
		groups: [
			{
				key: 'public-access',
				title: __( 'Public access', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.public',
						label: __(
							'Public facing pages — block by location',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'public.cache_bypass',
						label: __( 'Bypass page cache', 'ip-location-block' ),
						type: 'toggle',
						help: __(
							'Disable full-page caching so every visitor is geo-checked.',
							'ip-location-block'
						),
					},
				],
			},
			{
				key: 'country-matching',
				title: __( 'Country matching', 'ip-location-block' ),
				fields: [
					{
						path: 'public.matching_rule',
						label: __( 'Matching rule', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __(
									'Follow “Validation rules”',
									'ip-location-block'
								),
								value: '-1',
							},
							{
								label: __( 'Whitelist', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __( 'Blacklist', 'ip-location-block' ),
								value: '1',
							},
						],
					},
					{
						path: 'public.white_list',
						label: __(
							'Whitelist of country code',
							'ip-location-block'
						),
						type: 'text',
						showIf: ( s ) =>
							Number( s.public?.matching_rule ) === 0,
						help: __(
							'Comma-separated codes. Also accepts CC:State:Name / CC:City:Name (Region aliases State) and ~ for OR. Names must match the provider exactly — verify on the Search tab.',
							'ip-location-block'
						),
					},
					{
						path: 'public.black_list',
						label: __(
							'Blacklist of country code',
							'ip-location-block'
						),
						type: 'text',
						showIf: ( s ) =>
							Number( s.public?.matching_rule ) === 1,
						help: __(
							'Comma-separated codes. Also accepts CC:State:Name / CC:City:Name (Region aliases State) and ~ for OR. Names must match the provider exactly — verify on the Search tab.',
							'ip-location-block'
						),
					},
				],
			},
			{
				key: 'public-blocked-response',
				title: __( 'Blocked response', 'ip-location-block' ),
				fields: [
					{
						path: 'public.response_code',
						label: __(
							'Response code (RFC 2616)',
							'ip-location-block'
						),
						type: 'select',
						options: RESPONSE_CODES,
					},
					{
						path: 'public.redirect_uri',
						label: __( 'Redirect URL', 'ip-location-block' ),
						type: 'text',
					},
					{
						path: 'public.response_msg',
						label: __( 'Response message', 'ip-location-block' ),
						type: 'text',
					},
				],
			},
			{
				key: 'request-targeting',
				title: __( 'Request targeting', 'ip-location-block' ),
				fields: [
					{
						path: 'public.target_rule',
						label: __( 'Validation target', 'ip-location-block' ),
						type: 'select',
						options: [
							{
								label: __(
									'All requests',
									'ip-location-block'
								),
								value: '0',
							},
							{
								label: __(
									'Specify the targets',
									'ip-location-block'
								),
								value: '1',
							},
						],
					},
					{
						path: 'public.target_pages',
						label: __( 'Target pages', 'ip-location-block' ),
						type: 'checkbox-list',
						source: 'content.pages',
						shape: 'map',
						showIf: ( s ) => Number( s.public?.target_rule ) === 1,
					},
					{
						path: 'public.target_posts',
						label: __( 'Target post types', 'ip-location-block' ),
						type: 'checkbox-list',
						source: 'content.posts',
						shape: 'map',
						showIf: ( s ) => Number( s.public?.target_rule ) === 1,
					},
					{
						path: 'public.target_cates',
						label: __( 'Target categories', 'ip-location-block' ),
						type: 'checkbox-list',
						source: 'content.cates',
						shape: 'map',
						showIf: ( s ) => Number( s.public?.target_rule ) === 1,
					},
					{
						path: 'public.target_tags',
						label: __( 'Target tags', 'ip-location-block' ),
						type: 'checkbox-list',
						source: 'content.tags',
						shape: 'map',
						showIf: ( s ) => Number( s.public?.target_rule ) === 1,
					},
					{
						path: 'exception.public',
						label: __(
							'Excluded public actions',
							'ip-location-block'
						),
						type: 'csv',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
				],
			},
			{
				key: 'bot-protection',
				title: __( 'Bot protection', 'ip-location-block' ),
				fields: [
					{
						path: 'public.behavior',
						label: __(
							'Block badly-behaved bots and crawlers',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'behavior.view',
						label: __(
							'Condition — page views',
							'ip-location-block'
						),
						type: 'number',
					},
					{
						path: 'behavior.time',
						label: __( 'Condition — seconds', 'ip-location-block' ),
						type: 'number',
					},
					{
						path: 'public.ua_list',
						label: __(
							'User-Agent (bot) rules',
							'ip-location-block'
						),
						type: 'ua-rules',
						help: __(
							'Each rule is “UA<sep>qualifier”: “:” allows, “#” blocks; the UA is a case-sensitive substring (or “*”). Qualifiers: “*” any country, a 2-letter country code, HOST / HOST=name (verified reverse DNS), FEED, AS12345 (ASN), REF=text, or an IP/CIDR. A leading “!” negates. Allow-rules with HOST verify only when Reverse DNS lookup is on; otherwise they pass any matching UA from any country. Block-rules need no verification.',
							'ip-location-block'
						),
					},
					{
						path: 'public.dnslkup',
						label: __( 'Reverse DNS lookup', 'ip-location-block' ),
						type: 'toggle',
						help: __(
							'Required for HOST allow-rules to actually verify a bot (e.g. Googlebot). Off (default): HOST rules are masked to “any”, so they pass on the User-Agent alone. Adds a reverse-DNS query per checked visitor.',
							'ip-location-block'
						),
					},
				],
			},
		],
	},
	{
		key: 'recording',
		title: __( 'Privacy and record settings', 'ip-location-block' ),
		groups: [
			{
				key: 'privacy-controls',
				title: __( 'Privacy controls', 'ip-location-block' ),
				fields: [
					{
						path: 'anonymize',
						label: __(
							'Anonymize IP address',
							'ip-location-block'
						),
						type: 'toggle',
						tip: __(
							'Masks the last octet of recorded IPs for privacy compliance.',
							'ip-location-block'
						),
					},
				],
			},
			{
				key: 'ip-cache',
				title: __( 'IP cache', 'ip-location-block' ),
				fields: [
					{
						path: 'cache_hold',
						label: __(
							'Record “IP address cache”',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'cache_time',
						label: __(
							'Cache expiration time [sec]',
							'ip-location-block'
						),
						type: 'number',
					},
					{
						path: 'cache_time_gc',
						label: __( 'GC interval [sec]', 'ip-location-block' ),
						type: 'number',
						schedule: 'cleanup',
					},
				],
			},
			{
				key: 'validation-logs',
				title: __( 'Validation logs', 'ip-location-block' ),
				fields: [
					{
						path: 'validation.reclogs',
						label: __(
							'Record “Validation logs”',
							'ip-location-block'
						),
						type: 'select',
						options: [
							{
								label: __( 'Disable', 'ip-location-block' ),
								value: '0',
							},
							{
								label: __(
									'When blocked',
									'ip-location-block'
								),
								value: '1',
							},
							{
								label: __( 'When passed', 'ip-location-block' ),
								value: '2',
							},
							{
								label: __(
									'Blocked or passed (not whitelisted)',
									'ip-location-block'
								),
								value: '6',
							},
							{
								label: __(
									'Unauthenticated visitor',
									'ip-location-block'
								),
								value: '3',
							},
							{
								label: __(
									'Authenticated user',
									'ip-location-block'
								),
								value: '4',
							},
							{
								label: __(
									'All the validation',
									'ip-location-block'
								),
								value: '5',
							},
						],
					},
					{
						path: 'validation.explogs',
						label: __(
							'Logs expiration [days]',
							'ip-location-block'
						),
						type: 'number',
					},
					{
						path: 'validation.postkey',
						label: __(
							'$_POST keys to record',
							'ip-location-block'
						),
						type: 'text',
					},
					{
						path: 'validation.maxlogs',
						label: __( 'Maximum log entries', 'ip-location-block' ),
						type: 'number',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
					{
						path: 'live_update.in_memory',
						label: __(
							'Live log database source',
							'ip-location-block'
						),
						type: 'select',
						options: [
							{
								label: __(
									'Ordinary file',
									'ip-location-block'
								),
								value: '0',
							},
							{
								label: __( 'In-memory', 'ip-location-block' ),
								value: '1',
							},
						],
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug &&
							!! sources.context?.features?.pdoSqlite,
					},
				],
			},
			{
				key: 'statistics-cleanup',
				title: __( 'Statistics and cleanup', 'ip-location-block' ),
				fields: [
					{
						path: 'save_statistics',
						label: __(
							'Record “Statistics of validation”',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'validation.recdays',
						label: __(
							'Maximum statistics period [days]',
							'ip-location-block'
						),
						type: 'number',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
					{
						path: 'clean_uninstall',
						label: __(
							'Remove all settings and records at uninstall',
							'ip-location-block'
						),
						type: 'toggle',
					},
				],
			},
		],
	},
	{
		key: 'provider',
		title: __( 'Geolocation API settings', 'ip-location-block' ),
		groups: [
			{
				key: 'provider-configuration',
				fields: [
					{ path: '__precision', type: 'precision-upsell' },
					{
						path: 'providers',
						label: __(
							'API selection and key settings',
							'ip-location-block'
						),
						type: 'provider-table',
					},
					{
						path: 'timeout',
						label: __(
							'Network API timeout [sec]',
							'ip-location-block'
						),
						type: 'number',
						showIf: ( s, sources ) =>
							!! sources.context?.features?.debug,
					},
				],
			},
		],
	},
	{
		key: 'database',
		title: __( 'Local database settings', 'ip-location-block' ),
		groups: [
			{
				key: 'local-database',
				fields: [
					{
						path: 'update.auto',
						label: __(
							'Auto updating (once a month)',
							'ip-location-block'
						),
						type: 'toggle',
					},
					{
						path: 'IP2Location.ipv4_path',
						label: __(
							'Database file status',
							'ip-location-block'
						),
						type: 'db-status',
					},
				],
			},
		],
	},
	{
		key: 'others',
		title: __( 'Plugin settings', 'ip-location-block' ),
		groups: [
			{
				key: 'plugin-settings',
				fields: [
					{
						path: 'network_wide',
						label: __(
							'Network wide settings',
							'ip-location-block'
						),
						type: 'toggle',
						showIf: ( s, sources ) =>
							!! sources.context?.scope?.isMultisite &&
							!! sources.context?.scope?.isNetworkAdmin,
					},
					{
						path: '__pluginTools',
						type: 'settings-actions',
					},
				],
			},
		],
	},
];
