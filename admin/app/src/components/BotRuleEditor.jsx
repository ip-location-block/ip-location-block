/**
 * Bot-rule builder for `public.ua_list` — replaces the bare textarea.
 *
 * A controlled view over the SAME `public.ua_list` string the rest of the admin
 * saves. It keeps a local draft of editor rows (so blank / in-progress rows
 * survive re-renders that the serializer would otherwise drop) and writes the
 * serialized string back through onChange('public.ua_list', …); the shared Save
 * bar persists it. A raw-textarea escape hatch round-trips the same string.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	parseUaList,
	serializeUaList,
	serializeRow,
} from '../lib/uaRules';
import { syncUaDraft } from '../lib/uaDraft';
import {
	PRESETS,
	MODERN_DEFAULT,
	presetActive,
	togglePreset,
	isLegacyDefault,
} from '../lib/uaPresets';
import { evaluateUa, botCandidatesFromLogs } from '../lib/uaTester';
import { botByToken } from '../data/bots';
import { ALL_CODES, countryLabel } from '../data/countries';
import { getLogs } from '../api';

const MIGRATION_FLAG = 'ua_legacy_offer';

const COUNTRY_OPTIONS = ALL_CODES.map( ( cc ) => ( {
	label: countryLabel( cc ),
	value: cc,
} ) );

// Translatable preset copy, keyed by the (untranslated) preset id in uaPresets.
const PRESET_LABELS = {
	'verified-search': {
		label: __( 'Allow verified search engines', 'ip-location-block' ),
		help: __(
			'Googlebot, Bingbot, DuckDuckBot, Applebot. These verify only when reverse DNS is on (below).',
			'ip-location-block'
		),
	},
	feed: {
		label: __( 'Allow feed readers', 'ip-location-block' ),
		help: __(
			'Permit any feed (RSS/Atom) request regardless of the crawler.',
			'ip-location-block'
		),
	},
	social: {
		label: __( 'Allow social & link-preview bots', 'ip-location-block' ),
		help: __(
			'Facebook, X/Twitter, LinkedIn, Slack, Discord. Allowed by UA only (they fetch from cloud IPs).',
			'ip-location-block'
		),
	},
	'ai-training': {
		label: __( 'Block AI training crawlers', 'ip-location-block' ),
		help: __(
			'GPTBot, ClaudeBot, CCBot, Bytespider, Meta AI. Blocked by User-Agent — no verification needed.',
			'ip-location-block'
		),
	},
	seo: {
		label: __( 'Block aggressive SEO scrapers', 'ip-location-block' ),
		help: __(
			'AhrefsBot, SemrushBot, MJ12bot. High-volume commercial backlink crawlers.',
			'ip-location-block'
		),
	},
	'ai-agents': {
		label: __( 'Block AI agents (user-triggered fetchers)', 'ip-location-block' ),
		help: __(
			'ChatGPT-User, Claude-User, Perplexity-User, Meta fetcher. Live reads made on a user’s request.',
			'ip-location-block'
		),
	},
	'ai-search': {
		label: __( 'Allow AI search crawlers', 'ip-location-block' ),
		help: __(
			'OAI-SearchBot, Claude-SearchBot, PerplexityBot. Index your pages for AI answers with citations.',
			'ip-location-block'
		),
	},
};

const QUAL_OPTIONS = [
	{ label: __( 'Any country', 'ip-location-block' ), value: 'any' },
	{ label: __( 'Specific country', 'ip-location-block' ), value: 'country' },
	{
		label: __( 'Verified host (reverse DNS)', 'ip-location-block' ),
		value: 'host',
	},
	{ label: __( 'Feed request', 'ip-location-block' ), value: 'feed' },
	{ label: __( 'IP address / CIDR', 'ip-location-block' ), value: 'ip' },
	{ label: __( 'ASN', 'ip-location-block' ), value: 'asn' },
	{ label: __( 'Referer contains', 'ip-location-block' ), value: 'ref' },
];

const QUAL_VALUES = new Set( QUAL_OPTIONS.map( ( o ) => o.value ) );

// A recommended editor row for a catalog token (used by log detection).
const rowForToken = ( token ) => {
	const bot = botByToken( token );
	if ( ! bot ) {
		return { ua: token, action: 'block', qualType: 'any', qualValue: '', negate: false };
	}
	if ( bot.disposition === 'block' ) {
		return { ua: bot.token, action: 'block', qualType: 'any', qualValue: '', negate: false };
	}
	return {
		ua: bot.token,
		action: 'pass',
		qualType: bot.verification === 'host' ? 'host' : 'any',
		qualValue: '',
		negate: false,
	};
};

export default function BotRuleEditor( { value, settings, onChange, help } ) {
	const stored = value || '';
	const dnslkup = !! settings?.public?.dnslkup;

	const [ draft, setDraft ] = useState( () => parseUaList( stored ) );
	const lastWrittenRef = useRef( stored );
	useEffect( () => {
		setDraft( ( prev ) =>
			syncUaDraft( prev, stored, lastWrittenRef.current )
		);
	}, [ stored ] );

	const [ raw, setRaw ] = useState( false );
	const [ testUa, setTestUa ] = useState( '' );
	const [ detected, setDetected ] = useState( null );
	const [ detecting, setDetecting ] = useState( false );
	const [ detectError, setDetectError ] = useState( '' );

	// Write the serialized draft back to the stored string.
	const commit = ( nextDraft ) => {
		setDraft( nextDraft );
		const str = serializeUaList( nextDraft );
		lastWrittenRef.current = str;
		onChange( 'public.ua_list', str );
	};

	const updateRow = ( i, patch ) =>
		commit( draft.map( ( r, idx ) => ( idx === i ? { ...r, ...patch } : r ) ) );

	const removeRow = ( i ) =>
		commit( draft.filter( ( _, idx ) => idx !== i ) );

	const addRow = () =>
		commit( [
			...draft,
			{ ua: '', action: 'block', qualType: 'any', qualValue: '', negate: false },
		] );

	const onPresetToggle = ( preset, on ) =>
		commit( togglePreset( draft, preset, on ) );

	// --- migration offer ----------------------------------------------------
	const offerVisible =
		!! settings?.[ MIGRATION_FLAG ] && isLegacyDefault( stored );

	const applyMigration = () => {
		const nextDraft = parseUaList( MODERN_DEFAULT );
		setDraft( nextDraft );
		lastWrittenRef.current = MODERN_DEFAULT;
		onChange( 'public.ua_list', MODERN_DEFAULT );
		onChange( MIGRATION_FLAG, false );
	};

	const dismissMigration = () => onChange( MIGRATION_FLAG, false );

	// --- detect from logs ---------------------------------------------------
	const detect = () => {
		setDetecting( true );
		setDetectError( '' );
		getLogs()
			.then( ( data ) =>
				setDetected( botCandidatesFromLogs( data?.rows || [] ) )
			)
			.catch( ( e ) => setDetectError( e.message ) )
			.finally( () => setDetecting( false ) );
	};

	const addDetected = ( token ) => commit( [ ...draft, rowForToken( token ) ] );

	// --- UA tester ----------------------------------------------------------
	const testResult = testUa ? evaluateUa( testUa, draft ) : null;

	// ------------------------------------------------------------------------
	return (
		<div className="ilb-bot-rules">
			{ offerVisible && (
				<Notice
					status="info"
					isDismissible={ false }
					className="ilb-bot-rules__offer"
				>
					<strong>
						{ __(
							'Modernize your bot rules?',
							'ip-location-block'
						) }
					</strong>
					<p>
						{ __(
							'Your bot list is the old 2016 default. The modern set drops dead tokens (slurp, Facebot) and over-broad matches, and blocks AI-training and scraper crawlers (GPTBot, ClaudeBot, AhrefsBot…).',
							'ip-location-block'
						) }
					</p>
					<div className="ilb-bot-rules__offer-actions">
						<Button variant="primary" onClick={ applyMigration }>
							{ __(
								'Apply modern rules',
								'ip-location-block'
							) }
						</Button>
						<Button variant="tertiary" onClick={ dismissMigration }>
							{ __( 'Keep mine', 'ip-location-block' ) }
						</Button>
					</div>
				</Notice>
			) }

			<div className="ilb-bot-rules__mode">
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Raw list (advanced)', 'ip-location-block' ) }
					checked={ raw }
					onChange={ setRaw }
				/>
			</div>

			{ raw ? (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __(
						'User-Agent rules',
						'ip-location-block'
					) }
					help={ help }
					rows={ 6 }
					value={ stored }
					onChange={ ( v ) => onChange( 'public.ua_list', v ) }
				/>
			) : (
				<>
					{ /* Presets ------------------------------------------- */ }
					<div className="ilb-bot-rules__presets">
						<p className="ilb-bot-rules__section-title">
							{ __(
								'Recommended presets',
								'ip-location-block'
							) }
						</p>
						{ PRESETS.map( ( preset ) => {
							const copy = PRESET_LABELS[ preset.id ] || {
								label: preset.id,
							};
							return (
								<ToggleControl
									key={ preset.id }
									__nextHasNoMarginBottom
									className={ `ilb-bot-rules__preset ilb-bot-rules__preset--${ preset.group }` }
									label={ copy.label }
									help={ copy.help }
									checked={ presetActive( draft, preset ) }
									onChange={ ( on ) =>
										onPresetToggle( preset, on )
									}
								/>
							);
						} ) }
					</div>

					{ ! dnslkup && (
						<Notice
							status="warning"
							isDismissible={ false }
							className="ilb-bot-rules__dns-warning"
						>
							{ __(
								'Reverse DNS lookup is off, so “verified host (HOST)” allow-rules are NOT verified — they pass any request whose User-Agent contains the token, from any country. Turn on Reverse DNS lookup (below) to actually verify search-engine allow-rules. Block rules are unaffected.',
								'ip-location-block'
							) }
						</Notice>
					) }

					{ /* Advanced rows ------------------------------------- */ }
					<div className="ilb-bot-rules__rows">
						<p className="ilb-bot-rules__section-title">
							{ __(
								'Rules',
								'ip-location-block'
							) }
						</p>
						{ draft.length === 0 && (
							<p className="ilb-bot-rules__empty">
								{ __(
									'No rules yet. Turn on a preset above or add a rule.',
									'ip-location-block'
								) }
							</p>
						) }
						{ draft.map( ( row, i ) => (
							<div className="ilb-bot-rules__row" key={ i }>
								<TextControl
									__nextHasNoMarginBottom
									className="ilb-bot-rules__ua"
									label={ __(
										'User-Agent contains',
										'ip-location-block'
									) }
									placeholder="GPTBot"
									value={ row.ua }
									onChange={ ( v ) =>
										updateRow( i, { ua: v } )
									}
								/>
								<div
									className="ilb-view-switch ilb-bot-rules__action"
									role="group"
									aria-label={ __(
										'Action',
										'ip-location-block'
									) }
								>
									<button
										type="button"
										className="ilb-view-switch__option"
										aria-pressed={ row.action === 'block' }
										onClick={ () =>
											updateRow( i, { action: 'block' } )
										}
									>
										{ __( 'Block', 'ip-location-block' ) }
									</button>
									<button
										type="button"
										className="ilb-view-switch__option"
										aria-pressed={ row.action === 'pass' }
										onClick={ () =>
											updateRow( i, { action: 'pass' } )
										}
									>
										{ __( 'Allow', 'ip-location-block' ) }
									</button>
								</div>
								<SelectControl
									__nextHasNoMarginBottom
									className="ilb-bot-rules__qual"
									label={ __(
										'When',
										'ip-location-block'
									) }
									value={
										QUAL_VALUES.has( row.qualType )
											? row.qualType
											: 'other'
									}
									options={
										QUAL_VALUES.has( row.qualType )
											? QUAL_OPTIONS
											: [
													...QUAL_OPTIONS,
													{
														label: __(
															'Other (raw)',
															'ip-location-block'
														),
														value: 'other',
													},
											  ]
									}
									onChange={ ( v ) =>
										updateRow( i, {
											qualType: v,
											qualValue: '',
										} )
									}
								/>
								<div className="ilb-bot-rules__value">
									{ row.qualType === 'country' && (
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Country',
												'ip-location-block'
											) }
											value={ row.qualValue }
											options={ [
												{
													label: __(
														'Select…',
														'ip-location-block'
													),
													value: '',
												},
												...COUNTRY_OPTIONS,
											] }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
									{ row.qualType === 'host' && (
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'Host contains (optional)',
												'ip-location-block'
											) }
											placeholder="googlebot.com"
											value={ row.qualValue }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
									{ row.qualType === 'ip' && (
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'IP / CIDR',
												'ip-location-block'
											) }
											placeholder="203.0.113.0/24"
											value={ row.qualValue }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
									{ row.qualType === 'asn' && (
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'ASN',
												'ip-location-block'
											) }
											placeholder="AS15169"
											value={ row.qualValue }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
									{ row.qualType === 'ref' && (
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'Referer contains',
												'ip-location-block'
											) }
											placeholder="example.com"
											value={ row.qualValue }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
									{ row.qualType === 'other' && (
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'Raw qualifier',
												'ip-location-block'
											) }
											value={ row.qualValue }
											onChange={ ( v ) =>
												updateRow( i, {
													qualValue: v,
												} )
											}
										/>
									) }
								</div>
								<div className="ilb-bot-rules__row-meta">
									<CheckboxControl
										__nextHasNoMarginBottom
										label={ __(
											'NOT',
											'ip-location-block'
										) }
										checked={ !! row.negate }
										onChange={ ( v ) =>
											updateRow( i, { negate: v } )
										}
									/>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () => removeRow( i ) }
									>
										{ __(
											'Remove',
											'ip-location-block'
										) }
									</Button>
								</div>
								<code className="ilb-bot-rules__preview">
									{ serializeRow( row ) ||
										__(
											'(incomplete)',
											'ip-location-block'
										) }
								</code>
							</div>
						) ) }
						<div className="ilb-bot-rules__row-actions">
							<Button variant="secondary" onClick={ addRow }>
								{ __( 'Add a rule', 'ip-location-block' ) }
							</Button>
							<Button
								variant="tertiary"
								isBusy={ detecting }
								onClick={ detect }
							>
								{ __(
									'Scan recent logs for bots',
									'ip-location-block'
								) }
							</Button>
						</div>
						{ detectError && (
							<Notice status="error" isDismissible={ false }>
								{ detectError }
							</Notice>
						) }
						{ detected && detected.length === 0 && (
							<p className="ilb-bot-rules__empty">
								{ __(
									'No catalogued bots found in recent logs.',
									'ip-location-block'
								) }
							</p>
						) }
						{ detected && detected.length > 0 && (
							<div className="ilb-bot-rules__detected">
								{ detected.map( ( c ) => (
									<Button
										key={ c.token }
										variant="secondary"
										size="small"
										onClick={ () => addDetected( c.token ) }
									>
										{ sprintf(
											/* translators: 1: bot label, 2: hit count. */
											__(
												'+ %1$s (%2$d)',
												'ip-location-block'
											),
											c.label,
											c.count
										) }
									</Button>
								) ) }
							</div>
						) }
					</div>

					{ /* UA tester ----------------------------------------- */ }
					<div className="ilb-bot-rules__tester">
						<TextControl
							__nextHasNoMarginBottom
							label={ __(
								'Test a User-Agent',
								'ip-location-block'
							) }
							placeholder="Mozilla/5.0 (compatible; GPTBot/1.2; …)"
							value={ testUa }
							onChange={ setTestUa }
						/>
						{ testResult && (
							<div className="ilb-bot-rules__tester-result">
								<p
									className={ `ilb-bot-rules__verdict ilb-bot-rules__verdict--${ testResult.verdict }` }
								>
									{ testResult.verdict === 'block'
										? __(
												'Blocked by the User-Agent rules.',
												'ip-location-block'
										  )
										: testResult.verdict === 'pass'
										? __(
												'Allowed by the User-Agent rules.',
												'ip-location-block'
										  )
										: __(
												'No User-Agent rule decides this — country blocking applies.',
												'ip-location-block'
										  ) }
								</p>
								{ testResult.uncertain && (
									<p className="ilb-bot-rules__tester-note">
										{ __(
											'A matching rule uses a server-side check (country / host / IP), so the final result may differ on a real request.',
											'ip-location-block'
										) }
									</p>
								) }
								{ testResult.matches.length > 0 && (
									<ul className="ilb-bot-rules__tester-matches">
										{ testResult.matches.map( ( m ) => (
											<li key={ m.index }>
												<code>{ m.rule }</code> —{ ' ' }
												{ m.note }
											</li>
										) ) }
									</ul>
								) }
							</div>
						) }
					</div>
				</>
			) }
		</div>
	);
}
