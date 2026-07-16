/**
 * Attribution tab — geolocation provider credits. Ports the classic
 * "Attribution links" section: each provider's homepage + data-type note.
 */
import { useEffect, useState } from '@wordpress/element';
import { Card, CardBody, ExternalLink, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getProviders } from '../api';

export default function Attribution() {
	const [ providers, setProviders ] = useState( null );

	useEffect( () => {
		getProviders()
			.then( ( list ) => setProviders( list || [] ) )
			.catch( () => setProviders( [] ) );
	}, [] );

	if ( providers === null ) {
		return <Spinner />;
	}

	return (
		<div className="ilb-attribution">
			<p className="ilb-attribution__intro">
				{ __(
					'This plugin uses the following geolocation data providers. Please review each provider’s terms and give the appropriate attribution where required.',
					'ip-location-block'
				) }
			</p>

			<Card>
				<CardBody>
					<ul className="ilb-attribution__list">
						{ providers.map( ( p ) => (
							<li key={ p.name } className="ilb-attribution__item">
								<span className="ilb-attribution__name">{ p.name }</span>
								{ p.link ? (
									<ExternalLink href={ p.link } className="ilb-attribution__link">
										{ p.link }
									</ExternalLink>
								) : (
									<span className="ilb-attribution__link ilb-attribution__link--none">
										{ __( '—', 'ip-location-block' ) }
									</span>
								) }
								{ p.type && (
									<span className="ilb-attribution__type">{ p.type }</span>
								) }
							</li>
						) ) }
					</ul>
				</CardBody>
			</Card>
		</div>
	);
}
