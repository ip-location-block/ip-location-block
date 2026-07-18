/**
 * Renders one settings field based on its spec type. Value is read from /
 * written to the settings object by dot-path.
 *
 * Helper affordances ported from the classic admin:
 *  - field.tip        -> a "?" tooltip next to the label
 *  - field.optionDesc -> a dynamic description under a select, keyed by value
 *  - field.tool:'cidr'-> a CIDR<->range calculator button beside the field
 */
/* eslint-disable no-nested-ternary */
import { useState } from '@wordpress/element';
import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	Tooltip,
	Button,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getPath } from './paths';
import CheckboxList from '../components/CheckboxList';
import ProviderTable from '../components/ProviderTable';
import DatabaseStatus from '../components/DatabaseStatus';
import CidrCalculator from '../components/CidrCalculator';
import PrecisionUpsell from '../components/PrecisionUpsell';
import MimeTypeList from '../components/MimeTypeList';
import ExceptionEditor from '../components/ExceptionEditor';
import SettingsActions from './SettingsActions';
import { decodeLegacySignature } from '../settingsLogic';

// Label with an optional "?" tooltip. Passed as a node to the control's label.
const labelNode = ( field ) =>
	field.tip ? (
		<span className="ilb-field-label">
			{ field.label }
			<Tooltip text={ field.tip }>
				<span
					className="dashicons dashicons-editor-help ilb-field-tip"
					tabIndex="0"
					role="img"
					aria-label={ field.tip }
				/>
			</Tooltip>
		</span>
	) : (
		field.label
	);

export default function SettingsField( {
	field,
	settings,
	sources,
	onChange,
	onReplace,
	onRefreshSources,
} ) {
	const [ cidrOpen, setCidrOpen ] = useState( false );

	if ( field.showIf && ! field.showIf( settings, sources ) ) {
		return null;
	}

	const value = getPath( settings, field.path );
	const set = ( v ) => onChange( field.path, v );
	const labelId = `ilb-field-${ field.path.replace(
		/[^a-z0-9]+/gi,
		'-'
	) }-label`;

	// Non-input field types (own layout).
	if ( field.type === 'precision-upsell' ) {
		return (
			<div className="ilb-form-block ilb-form-block--upsell">
				<PrecisionUpsell
					settings={ settings }
					providers={ sources.providers }
					mode={ sources.mode }
				/>
			</div>
		);
	}
	if ( field.type === 'checkbox-list' ) {
		return (
			<div
				className="ilb-form-row ilb-form-row--checkbox-list"
				role="group"
				aria-labelledby={ labelId }
			>
				<strong id={ labelId } className="ilb-form-row__label">
					{ field.label }
				</strong>
				<div className="ilb-form-row__control">
					<CheckboxList
						items={ getPath( sources, field.source ) }
						value={ value }
						shape={ field.shape }
						onChange={ set }
					/>
				</div>
			</div>
		);
	}
	if ( field.type === 'provider-table' ) {
		return (
			<div className="ilb-form-block ilb-form-block--provider-table">
				<h3 className="ilb-form-block__title">{ field.label }</h3>
				<ProviderTable
					providers={ sources.providers }
					value={ value }
					status={ sources.providerStatus }
					onChange={ set }
				/>
			</div>
		);
	}
	if ( field.type === 'db-status' ) {
		return (
			<div className="ilb-form-block ilb-form-block--database">
				<h3 className="ilb-form-block__title">{ field.label }</h3>
				<DatabaseStatus
					rows={ sources.dbStatus }
					schedule={ sources.context?.schedules?.database }
					onRefresh={ onRefreshSources }
				/>
			</div>
		);
	}
	if ( field.type === 'mime-list' ) {
		return (
			<div
				className="ilb-form-row ilb-form-row--custom"
				role="group"
				aria-labelledby={ labelId }
			>
				<strong id={ labelId } className="ilb-form-row__label">
					{ field.label }
				</strong>
				<div className="ilb-form-row__control">
					<MimeTypeList
						items={ sources.context?.mimeTypes || [] }
						value={ value }
						onChange={ set }
					/>
				</div>
			</div>
		);
	}
	if ( field.type === 'exception-editor' ) {
		return (
			<div
				className="ilb-form-row ilb-form-row--custom"
				role="group"
				aria-labelledby={ labelId }
			>
				<strong id={ labelId } className="ilb-form-row__label">
					{ field.label }
				</strong>
				<div className="ilb-form-row__control">
					<ExceptionEditor
						target={ field.target }
						items={ getPath( sources, field.source ) || [] }
						value={ value }
						onChange={ set }
					/>
				</div>
			</div>
		);
	}
	if ( field.type === 'settings-actions' ) {
		return (
			<SettingsActions
				settings={ settings }
				context={ sources.context }
				onReplace={ onReplace }
				onContextChange={ onRefreshSources }
			/>
		);
	}

	if ( field.advanced ) {
		return (
			<div className="ilb-form-row ilb-form-row--advanced">
				<strong className="ilb-form-row__label">{ field.label }</strong>
				<em className="ilb-field-advanced">
					{ __(
						'Advanced field, coming soon in the Beta UI (use the classic screen for now).',
						'ip-location-block'
					) }
				</em>
			</div>
		);
	}

	// Dynamic per-option description wins over the static help when present.
	let dynamicHelp =
		( field.optionDesc && field.optionDesc[ String( value ) ] ) ||
		field.help;
	if ( field.schedule && sources.context?.schedules?.[ field.schedule ] ) {
		const schedule = sources.context.schedules[ field.schedule ];
		dynamicHelp =
			schedule.status === 'scheduled'
				? sprintf(
						/* translators: %s: next scheduled task date. */
						__( 'Next scheduled run: %s', 'ip-location-block' ),
						schedule.formatted
				  )
				: schedule.status === 'missing'
				? __(
						'The expected WP-Cron event is missing. Save the settings or reactivate the plugin to schedule it again.',
						'ip-location-block'
				  )
				: __(
						'Automatic scheduling is disabled.',
						'ip-location-block'
				  );
	}

	const common = {
		__nextHasNoMarginBottom: true,
		label: labelNode( field ),
		help: dynamicHelp,
	};
	const wrap = ( control, modifier = field.type ) => (
		<div className={ `ilb-form-row ilb-form-row--${ modifier }` }>
			{ control }
		</div>
	);

	switch ( field.type ) {
		case 'select':
			return wrap(
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					value={ String( value ?? '' ) }
					options={ field.options }
					onChange={ ( v ) =>
						set( /^-?\d+$/.test( v ) ? Number( v ) : v )
					}
				/>
			);
		case 'toggle':
			return wrap(
				<ToggleControl
					{ ...common }
					checked={ !! value }
					onChange={ set }
				/>
			);
		case 'bitmask':
			return wrap(
				<ToggleControl
					{ ...common }
					checked={ Number( value ) % 2 === 1 }
					onChange={ ( v ) => set( v ? 1 : 0 ) }
				/>,
				'toggle'
			);
		case 'number':
			return wrap(
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type="number"
					value={ value ?? '' }
					onChange={ ( v ) => set( v === '' ? '' : Number( v ) ) }
				/>
			);
		case 'textarea':
			return wrap(
				<div
					className={ `ilb-field-tooled${
						field.tool ? ' ilb-field-tooled--has-tool' : ''
					}` }
				>
					<TextareaControl
						{ ...common }
						help={ field.tool ? undefined : dynamicHelp }
						rows={ 3 }
						value={ value ?? '' }
						onChange={ set }
						aria-describedby={
							field.tool ? `${ labelId }-help` : undefined
						}
					/>
					{ field.tool === 'cidr' && (
						<>
							<div className="ilb-field-footer">
								{ dynamicHelp && (
									<p
										id={ `${ labelId }-help` }
										className="ilb-field-footer__help"
									>
										{ dynamicHelp }
									</p>
								) }
								<Button
									variant="secondary"
									size="small"
									icon="calculator"
									className="ilb-field-tool-btn"
									onClick={ () => setCidrOpen( true ) }
								>
									{ __(
										'CIDR calculator',
										'ip-location-block'
									) }
								</Button>
							</div>
							{ cidrOpen && (
								<CidrCalculator
									onClose={ () => setCidrOpen( false ) }
									onInsert={ ( text ) =>
										set(
											value
												? `${ value }\n${ text }`
												: text
										)
									}
								/>
							) }
						</>
					) }
				</div>,
				'textarea'
			);
		case 'signature': {
			const decoded = decodeLegacySignature( value );
			return wrap(
				<div className="ilb-field-tooled ilb-field-tooled--has-tool">
					<TextareaControl
						{ ...common }
						help={ undefined }
						rows={ 3 }
						value={ value ?? '' }
						onChange={ set }
					/>
					<div className="ilb-field-footer">
						{ dynamicHelp && (
							<p className="ilb-field-footer__help">
								{ dynamicHelp }
							</p>
						) }
						{ decoded && (
							<Button
								variant="secondary"
								size="small"
								icon="update"
								onClick={ () => set( decoded ) }
							>
								{ __(
									'Restore readable signatures',
									'ip-location-block'
								) }
							</Button>
						) }
					</div>
				</div>,
				'textarea'
			);
		}
		case 'csv':
			return wrap(
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					value={
						Array.isArray( value ) ? value.join( ',' ) : value ?? ''
					}
					onChange={ set }
				/>
			);
		case 'text':
		default:
			return wrap(
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					value={ value ?? '' }
					onChange={ set }
				/>
			);
	}
}
