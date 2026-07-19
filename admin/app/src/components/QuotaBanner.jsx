/**
 * Persistent, full-width warning banner for a blocking native-provider quota
 * status. Shown across every tab (the classic admin showed the API-key-upgrade
 * notice on every page). Data comes from the existing /providers/status quota;
 * dismissal is handled per incident by the parent (quotaBannerLogic).
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function QuotaBanner( { quota, onDismiss } ) {
	if ( ! quota ) {
		return null;
	}
	const upgrade = quota.status === 'key_upgrade_required';

	return (
		<div className="ilb-quota-banner" role="alert">
			<span className="dashicons dashicons-warning" aria-hidden="true" />
			<div className="ilb-quota-banner__message">
				<strong>
					{ __(
						'IP Location Block provider needs attention',
						'ip-location-block'
					) }
				</strong>
				<span>
					{ quota.message ||
						__(
							'The provider cannot currently answer geolocation requests.',
							'ip-location-block'
						) }
				</span>
			</div>
			<div className="ilb-quota-banner__actions">
				{ quota.upgradeUrl && (
					<Button
						variant="primary"
						href={ quota.upgradeUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ upgrade
							? __( 'Upgrade API key', 'ip-location-block' )
							: __( 'Upgrade plan', 'ip-location-block' ) }
					</Button>
				) }
				{ quota.accountUrl && (
					<Button
						variant="secondary"
						href={ quota.accountUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Manage account', 'ip-location-block' ) }
					</Button>
				) }
				<Button variant="tertiary" onClick={ onDismiss }>
					{ __( 'Dismiss', 'ip-location-block' ) }
				</Button>
			</div>
		</div>
	);
}
