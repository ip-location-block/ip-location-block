/**
 * Root component of the Beta admin. Tabs are added in WS4; this is the shell
 * that proves the toolchain (build → externalized wp.* deps → asset manifest).
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, __experimentalHeading as Heading } from '@wordpress/components';

export default function App() {
	return (
		<div className="ilb-app">
			<Card>
				<CardBody>
					<Heading level={ 2 }>
						{ __( 'IP Location Block', 'ip-location-block' ) }
					</Heading>
					<p>{ __( 'The new React admin is under construction.', 'ip-location-block' ) }</p>
				</CardBody>
			</Card>
		</div>
	);
}
