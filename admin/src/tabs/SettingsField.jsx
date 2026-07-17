/**
 * Renders one settings field based on its spec type. Value is read from /
 * written to the settings object by dot-path.
 *
 * Helper affordances ported from the classic admin:
 *  - field.tip        -> a "?" tooltip next to the label
 *  - field.optionDesc -> a dynamic description under a select, keyed by value
 *  - field.tool:'cidr'-> a CIDR<->range calculator button beside the field
 */
import { useState } from '@wordpress/element';
import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	Tooltip,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getPath } from './paths';
import CheckboxList from '../components/CheckboxList';
import ProviderTable from '../components/ProviderTable';
import DatabaseStatus from '../components/DatabaseStatus';
import CidrCalculator from '../components/CidrCalculator';
import ScanCountry from '../components/ScanCountry';
import PrecisionUpsell from '../components/PrecisionUpsell';

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

export default function SettingsField( { field, settings, sources, onChange } ) {
	const [ cidrOpen, setCidrOpen ] = useState( false );

	if ( field.showIf && ! field.showIf( settings ) ) {
		return null;
	}

	const value = getPath( settings, field.path );
	const set = ( v ) => onChange( field.path, v );

	// Non-input field types (own layout).
	if ( field.type === 'precision-upsell' ) {
		return <PrecisionUpsell settings={ settings } providers={ sources.providers } mode={ sources.mode } />;
	}
	if ( field.type === 'scan-country' ) {
		return <ScanCountry />;
	}
	if ( field.type === 'checkbox-list' ) {
		return (
			<div className="ilb-field-list">
				<strong className="ilb-field-list__label">{ field.label }</strong>
				<CheckboxList
					items={ getPath( sources, field.source ) }
					value={ value }
					shape={ field.shape }
					onChange={ set }
				/>
			</div>
		);
	}
	if ( field.type === 'provider-table' ) {
		return (
			<div className="ilb-field-list">
				<strong className="ilb-field-list__label">{ field.label }</strong>
				<ProviderTable providers={ sources.providers } value={ value } onChange={ set } />
			</div>
		);
	}
	if ( field.type === 'db-status' ) {
		return (
			<div className="ilb-field-list">
				<strong className="ilb-field-list__label">{ field.label }</strong>
				<DatabaseStatus rows={ sources.dbStatus } />
			</div>
		);
	}

	if ( field.advanced ) {
		return (
			<div className="ilb-field-advanced">
				<strong>{ field.label }</strong>{ ' ' }
				<em>
					{ __(
						'— advanced field, coming soon in the Beta UI (use the classic screen for now).',
						'ip-location-block'
					) }
				</em>
			</div>
		);
	}

	// Dynamic per-option description wins over the static help when present.
	const dynamicHelp =
		( field.optionDesc && field.optionDesc[ String( value ) ] ) || field.help;

	const common = {
		__nextHasNoMarginBottom: true,
		label: labelNode( field ),
		help: dynamicHelp,
	};

	switch ( field.type ) {
		case 'select':
			return (
				<SelectControl
					{ ...common }
					value={ String( value ?? '' ) }
					options={ field.options }
					onChange={ ( v ) => set( /^-?\d+$/.test( v ) ? Number( v ) : v ) }
				/>
			);
		case 'toggle':
			return <ToggleControl { ...common } checked={ !! value } onChange={ set } />;
		case 'bitmask':
			return (
				<ToggleControl
					{ ...common }
					checked={ ( Number( value ) & 1 ) === 1 }
					onChange={ ( v ) => set( v ? 1 : 0 ) }
				/>
			);
		case 'number':
			return (
				<TextControl
					{ ...common }
					type="number"
					value={ value ?? '' }
					onChange={ ( v ) => set( v === '' ? '' : Number( v ) ) }
				/>
			);
		case 'textarea':
			return (
				<div className="ilb-field-tooled">
					<TextareaControl { ...common } value={ value ?? '' } onChange={ set } />
					{ field.tool === 'cidr' && (
						<>
							<Button
								variant="secondary"
								size="small"
								icon="calculator"
								className="ilb-field-tool-btn"
								onClick={ () => setCidrOpen( true ) }
							>
								{ __( 'CIDR calculator', 'ip-location-block' ) }
							</Button>
							{ cidrOpen && (
								<CidrCalculator
									onClose={ () => setCidrOpen( false ) }
									onInsert={ ( text ) =>
										set( value ? `${ value }\n${ text }` : text )
									}
								/>
							) }
						</>
					) }
				</div>
			);
		case 'text':
		default:
			return <TextControl { ...common } value={ value ?? '' } onChange={ set } />;
	}
}
