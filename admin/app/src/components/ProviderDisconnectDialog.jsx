import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const NATIVE = 'IP Location Block';

export default function ProviderDisconnectDialog( {
	request,
	onCancel,
	onConfirm,
} ) {
	if ( ! request ) {
		return null;
	}

	const { provider, impact } = request;
	const fallbacks = impact.fallbackNames || [];
	let consequence;
	if ( fallbacks.length ) {
		consequence =
			provider === NATIVE
				? sprintf(
						/* translators: %s: comma-separated provider names. */
						__(
							'Country-level protection will continue through %s. Your regional rules will stay saved, but they will not run until IP Location Block is connected again.',
							'ip-location-block'
						),
						fallbacks.join( ', ' )
				  )
				: sprintf(
						/* translators: %s: comma-separated provider names. */
						__(
							'Protection will continue through %s. Your saved location rules will not be changed.',
							'ip-location-block'
						),
						fallbacks.join( ', ' )
				  );
	} else if ( impact.protectionWasEnabled ) {
		consequence = __(
			'This is your last ready geolocation provider. Public-site and wp-admin/login protection will be turned off when you save. Your location rules will be kept.',
			'ip-location-block'
		);
	} else {
		consequence = __(
			'Your location rules will be kept and can be used again after you connect a provider.',
			'ip-location-block'
		);
	}

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: provider name. */
				__( 'Disconnect %s?', 'ip-location-block' ),
				provider
			) }
			onRequestClose={ onCancel }
			className="ilb-provider-disconnect-modal"
		>
			<p>
				{ __(
					'The credential stored on this site will be removed when you save. This does not cancel your provider account or subscription.',
					'ip-location-block'
				) }
			</p>
			<p>{ consequence }</p>
			<div className="ilb-provider-disconnect-modal__actions">
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'ip-location-block' ) }
				</Button>
				<Button variant="primary" isDestructive onClick={ onConfirm }>
					{ __( 'Disconnect provider', 'ip-location-block' ) }
				</Button>
			</div>
		</Modal>
	);
}
