/**
 * Persist dismissal of the welcome notice wherever the classic admin bundle
 * is not loaded. WordPress core still owns the visual dismiss animation.
 */
( function () {
	const boot = window.ipLocationBlockWelcome;
	if ( ! boot || ! boot.endpoint || ! boot.nonce ) {
		return;
	}

	document.addEventListener(
		'click',
		function ( event ) {
			const button = event.target.closest( '.notice-dismiss' );
			if ( ! button ) {
				return;
			}

			const notice = button.closest( '.ip-location-block-notice-intro' );
			if ( ! notice ) {
				return;
			}

			const id = notice.getAttribute( 'data-notice' );
			if ( ! id ) {
				return;
			}

			window
				.fetch( boot.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					keepalive: true,
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': boot.nonce,
					},
					body: JSON.stringify( { id } ),
				} )
				.catch( function () {
					// Core has already hidden the notice. A failed request intentionally
					// leaves the campaign visible again on the next page load.
				} );
		},
		true
	);
} )();
