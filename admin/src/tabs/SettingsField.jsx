/**
 * Renders one settings field based on its spec type. Value is read from /
 * written to the settings object by dot-path.
 */
import { SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getPath } from './paths';
import CheckboxList from '../components/CheckboxList';
import ProviderTable from '../components/ProviderTable';
import DatabaseStatus from '../components/DatabaseStatus';

export default function SettingsField( { field, settings, sources, onChange } ) {
	if ( field.showIf && ! field.showIf( settings ) ) {
		return null;
	}

	const value = getPath( settings, field.path );
	const set = ( v ) => onChange( field.path, v );

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
	const common = {
		__nextHasNoMarginBottom: true,
		label: field.label,
		help: field.help,
	};

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
			return <TextareaControl { ...common } value={ value ?? '' } onChange={ set } />;
		case 'text':
		default:
			return <TextControl { ...common } value={ value ?? '' } onChange={ set } />;
	}
}
