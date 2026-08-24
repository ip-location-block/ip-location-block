/**
 * Inline CIDR <-> range calculator (wp-components Modal). Converts between an
 * IP range ("start - end") and a CIDR block list, IPv4 or IPv6. Optionally
 * inserts the CIDR result into the field that opened it.
 */
import { useState } from '@wordpress/element';
import {
	Modal,
	TextareaControl,
	Button,
	Flex,
	FlexItem,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { cidrToRange, rangeToCidr } from '../lib/cidr';

export default function CidrCalculator( { onClose, onInsert } ) {
	const [ range, setRange ] = useState( '' );
	const [ cidr, setCidr ] = useState( '' );
	const [ error, setError ] = useState( null );

	const toCidr = () => {
		setError( null );
		const raw = range.trim();
		if ( ! raw ) {
			return;
		}
		const [ a, b ] = raw.split( '-' ).map( ( s ) => s.trim() );
		const list = rangeToCidr( a, b || a );
		if ( ! list ) {
			setError( __( 'Enter a valid range, e.g. 192.168.0.0 - 192.168.255.255', 'ip-location-block' ) );
			return;
		}
		setCidr( list.join( '\n' ) );
	};

	const toRange = () => {
		setError( null );
		const first = cidr
			.split( /[\n,]/ )
			.map( ( s ) => s.trim() )
			.filter( Boolean )[ 0 ];
		if ( ! first ) {
			return;
		}
		const r = cidrToRange( first );
		if ( ! r ) {
			setError( __( 'Enter a valid CIDR, e.g. 192.168.0.0/16', 'ip-location-block' ) );
			return;
		}
		setRange( `${ r.start } - ${ r.end }` );
	};

	return (
		<Modal
			title={ __( 'CIDR ↔ Range calculator', 'ip-location-block' ) }
			onRequestClose={ onClose }
			className="ilb-cidr-modal"
		>
			{ error && (
				<Notice status="warning" isDismissible={ false } className="ilb-cidr-modal__error">
					{ error }
				</Notice>
			) }

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'IP range', 'ip-location-block' ) }
				value={ range }
				onChange={ setRange }
				rows={ 2 }
				placeholder="192.168.0.0 - 192.168.255.255"
			/>

			<Flex justify="center" gap={ 2 } className="ilb-cidr-modal__ops">
				<Button variant="secondary" onClick={ toCidr } icon="arrow-down-alt">
					{ __( 'To CIDR', 'ip-location-block' ) }
				</Button>
				<Button variant="secondary" onClick={ toRange } icon="arrow-up-alt">
					{ __( 'To range', 'ip-location-block' ) }
				</Button>
			</Flex>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'CIDR', 'ip-location-block' ) }
				value={ cidr }
				onChange={ setCidr }
				rows={ 4 }
				placeholder="192.168.0.0/16"
			/>

			<Flex justify="flex-end" gap={ 2 } className="ilb-cidr-modal__actions">
				{ onInsert && (
					<FlexItem>
						<Button
							variant="primary"
							disabled={ ! cidr.trim() }
							onClick={ () => {
								onInsert( cidr.trim() );
								onClose();
							} }
						>
							{ __( 'Insert into field', 'ip-location-block' ) }
						</Button>
					</FlexItem>
				) }
				<FlexItem>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Close', 'ip-location-block' ) }
					</Button>
				</FlexItem>
			</Flex>
		</Modal>
	);
}
