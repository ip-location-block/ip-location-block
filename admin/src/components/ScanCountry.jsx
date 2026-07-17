/**
 * "Scan country code" — queries every enabled provider for the current IP and
 * shows each one's country verdict. Ports the classic scan-code button.
 */
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { scanCountry } from '../api';

export default function ScanCountry() {
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const scan = () => {
		setBusy( true );
		setError( null );
		scanCountry()
			.then( ( r ) => setResult( r ) )
			.catch( ( e ) => setError( e.message || String( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="ilb-scan">
			<Button variant="secondary" isBusy={ busy } disabled={ busy } onClick={ scan } icon="search">
				{ __( 'Scan country code', 'ip-location-block' ) }
			</Button>

			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) } className="ilb-scan__notice">
					{ error }
				</Notice>
			) }

			{ result && (
				<div className="ilb-scan__result">
					<p className="ilb-scan__ip">
						{ __( 'Your IP:', 'ip-location-block' ) } <code>{ result.ip || '—' }</code>
					</p>
					<ul className="ilb-scan__list">
						{ ( result.providers || [] ).map( ( p ) => (
							<li key={ p.name }>
								<span className="ilb-scan__name">{ p.name }</span>
								<span className="ilb-scan__code">{ p.code || '—' }</span>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
}
