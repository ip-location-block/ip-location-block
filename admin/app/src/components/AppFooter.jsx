import { useState } from '@wordpress/element';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getAttributions } from '../api';

const boot = window.ipLocationBlockAdmin || {};

export default function AppFooter() {
	const [ open, setOpen ] = useState( false );
	const [ providers, setProviders ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const load = () => {
		if ( providers || loading ) {
			return;
		}
		setLoading( true );
		setError( '' );
		getAttributions()
			.then( ( result ) => setProviders( result || [] ) )
			.catch( ( requestError ) => setError( requestError.message ) )
			.finally( () => setLoading( false ) );
	};

	const show = () => {
		setOpen( true );
		load();
	};

	return (
		<>
			<footer className="ilb-app-footer">
				<div className="ilb-app-footer__inner">
					<span>
						{ __( 'IP Location Block', 'ip-location-block' ) }
						{ boot.version ? ` ${ boot.version }` : '' }
					</span>
					<span aria-hidden="true">·</span>
					<Button variant="link" onClick={ show }>
						{ __( 'Attributions', 'ip-location-block' ) }
					</Button>
				</div>
			</footer>

			{ open && (
				<Modal
					title={ __( 'Provider attributions', 'ip-location-block' ) }
					onRequestClose={ () => setOpen( false ) }
					className="ilb-attribution-modal"
				>
					<p className="ilb-attribution-modal__intro">
						{ __(
							'IP Location Block can use the following geolocation data providers. Review their terms and attribution requirements.',
							'ip-location-block'
						) }
					</p>
					{ loading && (
						<div className="ilb-attribution-modal__loading">
							<Spinner />
						</div>
					) }
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
							<Button variant="link" onClick={ load }>
								{ __( 'Retry', 'ip-location-block' ) }
							</Button>
						</Notice>
					) }
					{ providers && (
						<ul className="ilb-attribution-modal__list">
							{ providers.map( ( provider ) => (
								<li key={ provider.name }>
									<div className="ilb-attribution-modal__provider">
										<strong>{ provider.name }</strong>
										{ provider.active && (
											<span className="ilb-attribution-modal__active">
												{ __(
													'Active',
													'ip-location-block'
												) }
											</span>
										) }
									</div>
									{ provider.type && (
										<span>{ provider.type }</span>
									) }
									{ provider.link && (
										<a
											href={ provider.link }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ provider.link }
											<span
												className="dashicons dashicons-external"
												aria-hidden="true"
											/>
										</a>
									) }
								</li>
							) ) }
						</ul>
					) }
				</Modal>
			) }
		</>
	);
}
