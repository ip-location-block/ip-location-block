/**
 * Fixed toast stack for feedback from the shared settings save action.
 */
import { Snackbar } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const TOAST_TIMEOUT = 10000;

const ICONS = {
	success: 'yes-alt',
	warning: 'warning',
	error: 'no-alt',
};

function SaveToast( { notice, listRef, onRemove } ) {
	const persistent = notice.persistent !== false;
	const icon = ICONS[ notice.status ] || 'info-outline';

	useEffect( () => {
		if ( persistent ) {
			return undefined;
		}

		const timeout = window.setTimeout(
			() => onRemove( notice.id ),
			TOAST_TIMEOUT
		);
		return () => window.clearTimeout( timeout );
	}, [ notice.id, onRemove, persistent ] );

	return (
		<Snackbar
			className={ `ilb-save-toast is-${ notice.status }${
				persistent ? '' : ' is-timed'
			}` }
			explicitDismiss
			icon={
				<span
					className={ `dashicons dashicons-${ icon }` }
					aria-hidden="true"
				/>
			}
			listRef={ listRef }
			onRemove={ () => onRemove( notice.id ) }
			politeness={ notice.status === 'error' ? 'assertive' : 'polite' }
		>
			{ notice.message }
		</Snackbar>
	);
}

export default function SaveToastRegion( { notices = [], onRemove } ) {
	const regionRef = useRef( null );
	const hasNotices = notices.length > 0;

	return (
		<div
			ref={ regionRef }
			className="ilb-save-toasts"
			role={ hasNotices ? 'region' : undefined }
			aria-label={
				hasNotices
					? __( 'Settings save notifications', 'ip-location-block' )
					: undefined
			}
			tabIndex={ -1 }
		>
			{ notices.map( ( notice ) => (
				<SaveToast
					key={ notice.id }
					notice={ notice }
					listRef={ regionRef }
					onRemove={ onRemove }
				/>
			) ) }
		</div>
	);
}
