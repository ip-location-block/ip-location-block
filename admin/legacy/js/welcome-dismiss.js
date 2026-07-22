/**
 * Persist dismissal of the welcome notice on the React (Beta) screen.
 *
 * The classic admin bundle carries its own dismiss handler, but it is not
 * loaded here, so without this the core "is-dismissible" behaviour would only
 * hide the notice visually and it would return on the next page load.
 */
( function () {
	var boot = window.ipLocationBlockBeta;
	if ( ! boot || ! boot.restRoot || ! boot.nonce ) {
		return;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.notice-dismiss' );
		if ( ! button ) {
			return;
		}

		var notice = button.closest( '.ip-location-block-notice-intro' );
		if ( ! notice ) {
			return;
		}

		var id = notice.getAttribute( 'data-notice' );
		if ( ! id ) {
			return;
		}

		window.fetch( boot.restRoot + boot.restNamespace + '/notices/dismiss', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': boot.nonce,
			},
			body: JSON.stringify( { id: id } ),
		} );
	} );
} )();
