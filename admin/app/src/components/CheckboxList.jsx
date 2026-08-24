/**
 * A checkbox list of selectable values. Reads the current selection from
 * either a map ({ value: 1 }, e.g. content targets) or a simple array
 * (e.g. exception lists, which the server stores post-array_keys), and always
 * writes back the associative map { value: 1 } the classic sanitizer expects
 * (content keeps the map; exceptions are converted to a keys-array server-side).
 */
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function CheckboxList( { items, value, onChange } ) {
	if ( ! items || ! items.length ) {
		return <em className="ilb-checkbox-list__empty">{ __( 'None available.', 'ip-location-block' ) }</em>;
	}

	const selected = new Set(
		Array.isArray( value ) ? value : Object.keys( value || {} )
	);

	const emit = ( set ) =>
		onChange( [ ...set ].reduce( ( map, k ) => ( { ...map, [ k ]: 1 } ), {} ) );

	const toggle = ( v, on ) => {
		const next = new Set( selected );
		if ( on ) {
			next.add( v );
		} else {
			next.delete( v );
		}
		emit( next );
	};

	return (
		<div className="ilb-checkbox-list">
			{ items.map( ( item ) => (
				<CheckboxControl
					key={ item.value }
					__nextHasNoMarginBottom
					label={
						item.label +
						( item.active === false ? ` ${ __( '(inactive)', 'ip-location-block' ) }` : '' )
					}
					checked={ selected.has( item.value ) }
					onChange={ ( on ) => toggle( item.value, on ) }
				/>
			) ) }
		</div>
	);
}
