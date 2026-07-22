/* eslint-disable no-nested-ternary */
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getDiagnosticEnvironment } from '../api';
import { betaUrl } from '../navigation';
import {
	acknowledgedChecks,
	environmentText,
	orderedIssues,
	passedChecks,
} from '../diagnosticsLogic';

const STATUS_META = {
	critical: {
		icon: 'warning',
		label: __( 'Critical', 'ip-location-block' ),
	},
	warning: {
		icon: 'info-outline',
		label: __( 'Warning', 'ip-location-block' ),
	},
	info: {
		icon: 'info',
		label: __( 'Information', 'ip-location-block' ),
	},
	pass: {
		icon: 'yes-alt',
		label: __( 'Passed', 'ip-location-block' ),
	},
};

const actionHref = ( action ) =>
	action.target ? betaUrl( action.target ) : action.url;

function CheckActions( { check, onAcknowledge, acknowledging } ) {
	if ( check.status === 'pass' ) {
		return null;
	}

	return (
		<div className="ilb-diagnostic-check__actions">
			{ ( check.actions || [] ).map( ( action ) => (
				<Button
					key={ `${ action.label }-${ action.url || 'internal' }` }
					variant="secondary"
					href={ actionHref( action ) }
					target={ action.type === 'external' ? '_blank' : undefined }
					rel={
						action.type === 'external'
							? 'noopener noreferrer'
							: undefined
					}
				>
					{ action.label }
				</Button>
			) ) }
			{ check.acknowledgeable && (
				<Button
					variant="tertiary"
					isBusy={ acknowledging === check.id }
					disabled={ !! acknowledging }
					onClick={ () =>
						onAcknowledge( check.id, ! check.acknowledged )
					}
				>
					{ check.acknowledged
						? __( 'Restore advisory', 'ip-location-block' )
						: __( 'Acknowledge', 'ip-location-block' ) }
				</Button>
			) }
		</div>
	);
}

function DiagnosticCheck( props ) {
	const { check } = props;
	const meta = STATUS_META[ check.status ] || STATUS_META.info;

	return (
		<Card
			className={ `ilb-diagnostic-check is-${ check.status }${
				check.acknowledged ? ' is-acknowledged' : ''
			}` }
		>
			<CardBody>
				<div className="ilb-diagnostic-check__layout">
					<span
						className={ `dashicons dashicons-${ meta.icon } ilb-diagnostic-check__icon` }
						aria-hidden="true"
					/>
					<div className="ilb-diagnostic-check__content">
						<div className="ilb-diagnostic-check__heading">
							<h3>{ check.title }</h3>
							<span className="ilb-diagnostic-check__status">
								{ check.acknowledged
									? __( 'Acknowledged', 'ip-location-block' )
									: meta.label }
							</span>
						</div>
						<p>{ check.message }</p>
						{ check.details?.length > 0 && (
							<ul className="ilb-diagnostic-check__details">
								{ check.details.map( ( detail ) => (
									<li key={ detail }>{ detail }</li>
								) ) }
							</ul>
						) }
						<CheckActions { ...props } />
					</div>
				</div>
			</CardBody>
		</Card>
	);
}

function EmergencyAccessStatus( { status } ) {
	if ( ! status ) {
		return null;
	}
	const ready = status.state === 'ready';
	const outdated = status.state === 'outdated';
	const action = status.manage;

	return (
		<div
			className={ `ilb-emergency-status is-${ status.state }` }
			role={ outdated ? 'alert' : undefined }
		>
			<span
				className={ `dashicons dashicons-${
					ready ? 'yes-alt' : outdated ? 'warning' : 'admin-links'
				}` }
				aria-hidden="true"
			/>
			<div>
				<h2>{ __( 'Emergency access', 'ip-location-block' ) }</h2>
				<p>
					{ ready
						? __(
								'A private emergency login link is configured and current.',
								'ip-location-block'
						  )
						: outdated
						? __(
								'The saved emergency login link is outdated and should be regenerated.',
								'ip-location-block'
						  )
						: __(
								'No emergency login link is configured. Creating one gives you a recovery route if a rule locks you out.',
								'ip-location-block'
						  ) }
				</p>
			</div>
			{ action && (
				<Button variant="secondary" href={ actionHref( action ) }>
					{ action.label }
				</Button>
			) }
		</div>
	);
}

function SupportInformation() {
	const [ environment, setEnvironment ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ copied, setCopied ] = useState( false );

	const load = () => {
		if ( environment || loading ) {
			return;
		}
		setLoading( true );
		setError( '' );
		getDiagnosticEnvironment()
			.then( setEnvironment )
			.catch( ( requestError ) => setError( requestError.message ) )
			.finally( () => setLoading( false ) );
	};

	const copy = async () => {
		const text = environmentText( environment );
		try {
			if ( window.navigator.clipboard?.writeText ) {
				await window.navigator.clipboard.writeText( text );
			} else {
				const textarea = document.createElement( 'textarea' );
				textarea.value = text;
				textarea.setAttribute( 'readonly', '' );
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild( textarea );
				textarea.select();
				document.execCommand( 'copy' );
				document.body.removeChild( textarea );
			}
			setCopied( true );
			window.setTimeout( () => setCopied( false ), 2000 );
		} catch ( copyError ) {
			setError( copyError.message );
		}
	};

	return (
		<details
			className="ilb-diagnostics-disclosure ilb-support-info"
			onToggle={ ( event ) => event.currentTarget.open && load() }
		>
			<summary>
				<span>
					{ __(
						'Environment and support information',
						'ip-location-block'
					) }
				</span>
				<span className="ilb-diagnostics-disclosure__hint">
					{ __(
						'Includes active plugins · load on demand',
						'ip-location-block'
					) }
				</span>
			</summary>
			<div className="ilb-diagnostics-disclosure__body">
				<p className="ilb-support-info__privacy">
					<span
						className="dashicons dashicons-privacy"
						aria-hidden="true"
					/>
					{ __(
						'This report can contain server paths, active plugin names, and recent request details. Review it before sharing.',
						'ip-location-block'
					) }
				</p>
				{ loading && <Spinner /> }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
						<Button variant="link" onClick={ load }>
							{ __( 'Retry', 'ip-location-block' ) }
						</Button>
					</Notice>
				) }
				{ environment && (
					<>
						<div className="ilb-support-info__toolbar">
							<Button variant="secondary" onClick={ copy }>
								{ copied
									? __( 'Copied', 'ip-location-block' )
									: __( 'Copy report', 'ip-location-block' ) }
							</Button>
						</div>
						{ environment.sections.map( ( section ) => (
							<section
								key={ section.id }
								className="ilb-support-info__section"
							>
								<h3>{ section.title }</h3>
								<dl>
									{ section.rows.map( ( row, index ) => (
										<div
											key={ `${ row.label }-${ index }` }
										>
											<dt>{ row.label }</dt>
											<dd>{ row.value }</dd>
										</div>
									) ) }
								</dl>
							</section>
						) ) }
					</>
				) }
			</div>
		</details>
	);
}

export default function Diagnostics( {
	report,
	loading,
	error,
	onRefresh,
	onAcknowledge,
	acknowledging,
} ) {
	if ( loading && ! report ) {
		return (
			<div className="ilb-diagnostics__loading">
				<Spinner />
			</div>
		);
	}

	if ( error && ! report ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'Diagnostics could not be loaded.',
					'ip-location-block'
				) }{ ' ' }
				{ error }
				<Button variant="link" onClick={ onRefresh }>
					{ __( 'Retry', 'ip-location-block' ) }
				</Button>
			</Notice>
		);
	}

	const issues = orderedIssues( report );
	const acknowledged = acknowledgedChecks( report );
	const passed = passedChecks( report );
	const status = report?.status || 'healthy';
	const checkedAt = report?.checkedAt
		? new Date( report.checkedAt ).toLocaleString()
		: '';

	return (
		<div className="ilb-diagnostics">
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The latest refresh failed; showing the previous report.',
						'ip-location-block'
					) }{ ' ' }
					{ error }
				</Notice>
			) }
			<Card
				className={ `ilb-panel-shell ilb-diagnostics-summary is-${ status }` }
			>
				<CardHeader className="ilb-panel-shell__header">
					<div>
						<h2 className="ilb-panel-shell__title">
							{ __(
								'Site health diagnostics',
								'ip-location-block'
							) }
						</h2>
						<p className="ilb-panel-shell__description">
							{ __(
								'Configuration, provider, compatibility, and current-login safety checks.',
								'ip-location-block'
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						isBusy={ loading }
						onClick={ onRefresh }
					>
						{ __( 'Run checks', 'ip-location-block' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<div className="ilb-diagnostics-summary__body">
						<div className="ilb-diagnostics-summary__state">
							<span
								className={ `dashicons dashicons-${
									status === 'critical'
										? 'warning'
										: status === 'warning'
										? 'info-outline'
										: 'yes-alt'
								}` }
								aria-hidden="true"
							/>
							<strong>
								{ status === 'critical'
									? __(
											'Immediate action required',
											'ip-location-block'
									  )
									: status === 'warning'
									? __(
											'Review recommended',
											'ip-location-block'
									  )
									: __(
											'All actionable checks passed',
											'ip-location-block'
									  ) }
							</strong>
						</div>
						<div className="ilb-diagnostics-summary__counts">
							<span>
								<strong>{ report.counts.critical }</strong>{ ' ' }
								{ __( 'Critical', 'ip-location-block' ) }
							</span>
							<span>
								<strong>{ report.counts.warning }</strong>{ ' ' }
								{ __( 'Warnings', 'ip-location-block' ) }
							</span>
							<span>
								<strong>{ report.counts.passed }</strong>{ ' ' }
								{ __( 'Passed', 'ip-location-block' ) }
							</span>
						</div>
						{ checkedAt && (
							<p className="ilb-diagnostics-summary__checked">
								{ sprintf(
									/* translators: %s: localized date and time. */
									__(
										'Last checked: %s',
										'ip-location-block'
									),
									checkedAt
								) }
							</p>
						) }
					</div>
				</CardBody>
			</Card>

			<EmergencyAccessStatus status={ report.emergencyAccess } />

			<div className="ilb-diagnostics__issues">
				{ issues.length ? (
					issues.map( ( check ) => (
						<DiagnosticCheck
							key={ check.id }
							check={ check }
							onAcknowledge={ onAcknowledge }
							acknowledging={ acknowledging }
						/>
					) )
				) : (
					<div className="ilb-diagnostics__empty">
						<span
							className="dashicons dashicons-shield-alt"
							aria-hidden="true"
						/>
						<div>
							<h2>
								{ __(
									'No active diagnostic issues',
									'ip-location-block'
								) }
							</h2>
							<p>
								{ __(
									'The current configuration passed every actionable health check.',
									'ip-location-block'
								) }
							</p>
						</div>
					</div>
				) }
			</div>

			{ acknowledged.length > 0 && (
				<details className="ilb-diagnostics-disclosure">
					<summary>
						<span>
							{ __(
								'Acknowledged advisories',
								'ip-location-block'
							) }
						</span>
						<span className="ilb-diagnostics-disclosure__count">
							{ acknowledged.length }
						</span>
					</summary>
					<div className="ilb-diagnostics-disclosure__body ilb-diagnostics-disclosure__checks">
						{ acknowledged.map( ( check ) => (
							<DiagnosticCheck
								key={ check.id }
								check={ check }
								onAcknowledge={ onAcknowledge }
								acknowledging={ acknowledging }
							/>
						) ) }
					</div>
				</details>
			) }

			<details className="ilb-diagnostics-disclosure">
				<summary>
					<span>{ __( 'Passed checks', 'ip-location-block' ) }</span>
					<span className="ilb-diagnostics-disclosure__count">
						{ passed.length }
					</span>
				</summary>
				<div className="ilb-diagnostics-passed">
					{ passed.map( ( check ) => (
						<div
							key={ check.id }
							className="ilb-diagnostics-passed__item"
						>
							<span
								className="dashicons dashicons-yes-alt"
								aria-hidden="true"
							/>
							<div>
								<strong>{ check.title }</strong>
								<p>{ check.message }</p>
							</div>
						</div>
					) ) }
				</div>
			</details>

			<SupportInformation />
		</div>
	);
}
