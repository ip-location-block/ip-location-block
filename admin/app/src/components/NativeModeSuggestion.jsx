import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { dismissNativePromo } from '../api';
import { PATTERNS_URL } from './PrecisionContent';

export default function NativeModeSuggestion( {
	onConnect,
	onDismiss,
	connectDisabled = false,
} ) {
	const boot = ( window.ipLocationBlockAdmin ||= {} );
	const [ dismissed, setDismissed ] = useState(
		() => !! boot.nativePromoDismissed
	);
	const [ dismissing, setDismissing ] = useState( false );
	const [ failed, setFailed ] = useState( false );

	const dismiss = async () => {
		setDismissing( true );
		setFailed( false );
		try {
			const result = await dismissNativePromo();
			if ( ! result.dismissed ) {
				setFailed( true );
				return;
			}
			// Keep the server-provided preference current across tab/view remounts.
			boot.nativePromoDismissed = true;
			setDismissed( true );
			onDismiss?.();
		} catch {
			setFailed( true );
		} finally {
			setDismissing( false );
		}
	};

	if ( dismissed ) {
		return null;
	}

	return (
		<section
			className="ilb-provider-promo"
			aria-labelledby="ilb-native-suggestion-title"
		>
			<span
				className="ilb-provider-promo__icon dashicons dashicons-location-alt"
				aria-hidden="true"
			/>
			<div className="ilb-provider-promo__content">
				<span className="ilb-provider-promo__eyebrow">
					{ __( 'Native Mode · Optional', 'ip-location-block' ) }
				</span>
				<h3 id="ilb-native-suggestion-title">
					{ __( 'State and region blocking', 'ip-location-block' ) }
				</h3>
				<p>
					{ __(
						'Native Mode adds state and region rules using frequently updated geolocation data.',
						'ip-location-block'
					) }
				</p>
				<div className="ilb-provider-promo__actions">
					<a href={ PATTERNS_URL } target="_blank" rel="noreferrer">
						{ __( 'Learn more', 'ip-location-block' ) }
						<span
							className="dashicons dashicons-external"
							aria-hidden="true"
						/>
						<span className="screen-reader-text">
							{ ' ' }
							{ __(
								'(opens in a new tab)',
								'ip-location-block'
							) }
						</span>
					</a>
					<Button
						variant="link"
						disabled={ connectDisabled }
						onClick={ onConnect }
					>
						{ __( 'Connect API key', 'ip-location-block' ) }
					</Button>
				</div>
				{ failed && (
					<p className="ilb-provider-promo__error" role="alert">
						{ __(
							'Could not hide this suggestion. Please try again.',
							'ip-location-block'
						) }
					</p>
				) }
			</div>
			<Button
				className="ilb-provider-promo__dismiss"
				icon="no-alt"
				label={ __(
					'Hide this suggestion for this site',
					'ip-location-block'
				) }
				title={ __(
					'Hidden for all administrators on this site, including after updates.',
					'ip-location-block'
				) }
				disabled={ dismissing }
				onClick={ dismiss }
			/>
		</section>
	);
}
