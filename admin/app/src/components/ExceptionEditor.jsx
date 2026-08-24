import { useMemo, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	SearchControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getDetectedExceptions } from '../api';

const valuesFrom = ( value ) =>
	new Set( Array.isArray( value ) ? value : Object.keys( value || {} ) );

export default function ExceptionEditor( {
	target,
	items = [],
	value,
	onChange,
} ) {
	const [ detected, setDetected ] = useState( [] );
	const [ query, setQuery ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const selected = valuesFrom( value );
	const candidates = useMemo( () => {
		const merged = new Map();
		items.forEach( ( item ) => merged.set( item.value, item ) );
		detected.forEach( ( item ) =>
			merged.set( item.value, {
				value: item.value,
				label: item.value,
				context: item.context,
				detected: true,
			} )
		);
		selected.forEach( ( item ) => {
			if ( ! merged.has( item ) ) {
				merged.set( item, { value: item, label: item, custom: true } );
			}
		} );
		const needle = query.trim().toLowerCase();
		return [ ...merged.values() ].filter(
			( item ) =>
				! needle ||
				item.label.toLowerCase().includes( needle ) ||
				String( item.context || '' )
					.toLowerCase()
					.includes( needle )
		);
	}, [ detected, items, query, selected ] );

	const emit = ( next ) => {
		const values = [ ...next ];
		onChange(
			target === 'admin'
				? values
				: values.reduce(
						( map, key ) => ( { ...map, [ key ]: 1 } ),
						{}
				  )
		);
	};

	const toggle = ( key, checked ) => {
		const next = new Set( selected );
		if ( checked ) {
			next.add( key );
		} else {
			next.delete( key );
		}
		emit( next );
	};

	const detect = () => {
		setBusy( true );
		setError( '' );
		getDetectedExceptions( target )
			.then( ( response ) => setDetected( response.items || [] ) )
			.catch( ( requestError ) => setError( requestError.message ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="ilb-exception-editor">
			{ target === 'admin' && (
				<TextControl
					__nextHasNoMarginBottom
					label={ __(
						'Manual action or page names',
						'ip-location-block'
					) }
					help={ __(
						'Comma-separated action or page values remain available even when WordPress has not registered them yet.',
						'ip-location-block'
					) }
					value={ [ ...selected ].join( ', ' ) }
					onChange={ ( input ) =>
						emit(
							new Set(
								input
									.split( ',' )
									.map( ( item ) => item.trim() )
									.filter( Boolean )
							)
						)
					}
				/>
			) }
			<div className="ilb-exception-editor__toolbar">
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search candidates', 'ip-location-block' ) }
					value={ query }
					onChange={ setQuery }
				/>
				<Button variant="secondary" isBusy={ busy } onClick={ detect }>
					{ __( 'Find in blocked logs', 'ip-location-block' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ detected.length > 0 && (
				<p className="ilb-exception-editor__found">
					{ sprintf(
						/* translators: %d: number of exception candidates found in blocked logs. */
						__(
							'%d blocked-log candidate(s) found.',
							'ip-location-block'
						),
						detected.length
					) }
				</p>
			) }
			<div className="ilb-exception-editor__list">
				{ candidates.map( ( item ) => (
					<div
						key={ item.value }
						className="ilb-exception-editor__item"
					>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={
								item.label +
								( item.active === false
									? ` ${ __(
											'(inactive)',
											'ip-location-block'
									  ) }`
									: '' )
							}
							checked={ selected.has( item.value ) }
							onChange={ ( checked ) =>
								toggle( item.value, checked )
							}
						/>
						{ target === 'admin' && !! item.access && (
							<span className="ilb-exception-editor__access">
								{ [ 1, 3 ].includes( Number( item.access ) )
									? __( 'Signed-in', 'ip-location-block' )
									: '' }
								{ Number( item.access ) === 3 ? ' · ' : '' }
								{ [ 2, 3 ].includes( Number( item.access ) )
									? __( 'Public', 'ip-location-block' )
									: '' }
							</span>
						) }
						{ item.detected && item.context && (
							<span className="ilb-exception-editor__access">
								{ item.context }
							</span>
						) }
					</div>
				) ) }
				{ ! candidates.length && (
					<em>
						{ __(
							'No candidates match the search.',
							'ip-location-block'
						) }
					</em>
				) }
			</div>
		</div>
	);
}
