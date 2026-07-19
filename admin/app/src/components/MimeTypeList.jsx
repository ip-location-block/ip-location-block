import { useMemo, useState } from '@wordpress/element';
import { Button, CheckboxControl, SearchControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { selectedMimeMap } from '../settingsLogic';

export default function MimeTypeList( { items = [], value = {}, onChange } ) {
	const [ query, setQuery ] = useState( '' );
	const selected = selectedMimeMap( items, value );
	const visible = useMemo( () => {
		const needle = query.trim().toLowerCase();
		return needle
			? items.filter(
					( item ) =>
						item.extension.toLowerCase().includes( needle ) ||
						item.mime.toLowerCase().includes( needle )
			  )
			: items;
	}, [ items, query ] );

	const toggle = ( item, checked ) => {
		const next = { ...selected };
		if ( checked ) {
			next[ item.extension ] = item.mime;
		} else {
			delete next[ item.extension ];
		}
		onChange( next );
	};

	const selectVisible = () => {
		const next = { ...selected };
		visible.forEach( ( item ) => {
			next[ item.extension ] = item.mime;
		} );
		onChange( next );
	};

	const clearVisible = () => {
		const next = { ...selected };
		visible.forEach( ( item ) => delete next[ item.extension ] );
		onChange( next );
	};

	return (
		<details className="ilb-option-disclosure ilb-mime-list">
			<summary>
				<span>{ __( 'Allowed MIME types', 'ip-location-block' ) }</span>
				<span>
					{ sprintf(
						/* translators: %d: number of selected MIME types. */
						__( '%d selected', 'ip-location-block' ),
						Object.keys( selected ).length
					) }
				</span>
			</summary>
			<div className="ilb-option-disclosure__body">
				<div className="ilb-option-disclosure__toolbar">
					<SearchControl
						__nextHasNoMarginBottom
						label={ __( 'Search MIME types', 'ip-location-block' ) }
						value={ query }
						onChange={ setQuery }
					/>
					<Button variant="secondary" onClick={ selectVisible }>
						{ __( 'Select shown', 'ip-location-block' ) }
					</Button>
					<Button variant="tertiary" onClick={ clearVisible }>
						{ __( 'Clear shown', 'ip-location-block' ) }
					</Button>
				</div>
				<div className="ilb-mime-list__items">
					{ visible.map( ( item ) => (
						<CheckboxControl
							key={ item.extension }
							__nextHasNoMarginBottom
							label={ item.extension }
							help={ item.mime }
							checked={ Object.prototype.hasOwnProperty.call(
								selected,
								item.extension
							) }
							onChange={ ( checked ) => toggle( item, checked ) }
						/>
					) ) }
					{ ! visible.length && (
						<em>
							{ __(
								'No MIME types match the search.',
								'ip-location-block'
							) }
						</em>
					) }
				</div>
			</div>
		</details>
	);
}
