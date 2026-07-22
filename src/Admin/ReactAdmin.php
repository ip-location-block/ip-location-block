<?php
/**
 * IP Location Block - React admin.
 *
 * The React app is now the DEFAULT interface: it renders directly on the
 * plugin's own admin page (`page=ip-location-block`), the same slug the frozen
 * classic admin registers. There is a single admin-menu entry; the classic UI
 * stays reachable through a floating "Classic view" switcher whose choice is
 * remembered per user (user meta) and is deprecated for removal in a future
 * major release.
 *
 * Because both views live on one slug, "is this the React screen?" can no
 * longer be derived from the slug alone. This class owns the single view
 * resolver (current_view / is_react_screen) that every notice/enqueue guard
 * keys on, and it emits the classic-side switcher + deprecation bar additively
 * (no edits to the frozen admin file for that part).
 *
 * Requires WordPress 5.0+ (wp-element / React). On older WP the resolver always
 * returns 'classic' so the classic admin remains the only interface.
 *
 * The legacy class name IP_Location_Block_Beta is kept working via class_alias
 * in compat/legacy-aliases.php. Unlike the frozen classic admin, this class is
 * new code (introduced alongside the React admin) and is therefore namespaced
 * + written to modern PHP 8.1 idioms rather than kept under its legacy
 * identity.
 *
 * @package IP_Location_Block
 */

declare(strict_types=1);

namespace IPLocationBlock\Admin;

/**
 * Class ReactAdmin
 */
final class ReactAdmin {

	/**
	 * The plugin's admin page slug. Shared with the frozen classic admin, which
	 * registers this page; also reused as the React script/style handle.
	 */
	public const SLUG = 'ip-location-block';

	/**
	 * Pre-1.4.0 opt-in slug. Kept only so old bookmarks 302 to the merged page.
	 */
	public const LEGACY_SLUG = 'ip-location-block-beta';

	/**
	 * Per-user view preference ('new' | 'classic').
	 */
	private const VIEW_META = 'ip_location_block_admin_ui';

	/**
	 * Nonce action guarding view-preference persistence.
	 */
	private const VIEW_NONCE = 'ip-location-block-view-switch';

	private static ?self $instance = null;

	/**
	 * Per-request memoized resolved view ('new' | 'classic'), or null when not
	 * yet resolved.
	 */
	private static ?string $view = null;

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		// Redirect pre-1.4.0 bookmarks of the opt-in slug to the merged page.
		// MUST run on admin_menu (priority 0), not admin_init: the opt-in slug no
		// longer registers a page, so core's user_can_access_admin_page() check
		// in wp-admin/includes/menu.php wp_die(403)s the request while building
		// the menu — before admin_init ever fires. The admin_menu action fires
		// earlier in that same file, so the redirect wins.
		add_action( 'admin_menu', array( $this, 'maybe_redirect_legacy_slug' ), 0 );
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'add_menu' ) );
			add_action( 'network_admin_menu', array( $this, 'maybe_redirect_legacy_slug' ), 0 );
		}
	}

	/**
	 * The React UI needs the wp-element (React) runtime shipped since WP 5.0.
	 */
	public static function is_supported(): bool {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	/* ---------------------------------------------------------------------
	 * View resolver — the single source of truth for "which UI renders".
	 * ------------------------------------------------------------------- */

	/**
	 * Resolve the active view ('new' | 'classic'). Resolution order:
	 *   1. explicit ?view= (applies for the request; persists only with a valid
	 *      switch nonce — CSRF-safe self-persisting switcher links),
	 *   2. stored per-user preference (user meta),
	 *   3. default 'new'.
	 * Memoized per request. On WP < 5.0 always 'classic'.
	 */
	public static function current_view(): string {
		if ( null !== self::$view ) {
			return self::$view;
		}

		if ( ! self::is_supported() ) {
			return self::$view = 'classic';
		}

		$requested = self::requested_view();
		if ( '' !== $requested ) {
			self::maybe_persist_view( $requested );

			return self::$view = $requested;
		}

		$stored = self::stored_view();
		if ( '' !== $stored ) {
			return self::$view = $stored;
		}

		return self::$view = 'new';
	}

	/**
	 * True on the plugin's own admin page AND with the React view active. This
	 * is the one detector that replaces every former beta-slug check.
	 */
	public static function is_react_screen(): bool {
		return self::is_our_page() && 'new' === self::current_view();
	}

	/**
	 * Whether the current request targets the plugin's merged admin page. Keys
	 * off $_GET['page'] primarily so it is correct before get_current_screen()
	 * exists (e.g. during admin_menu / early admin_init).
	 */
	private static function is_our_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::SLUG === $page ) {
			return true;
		}

		// Fallback for the rare screens loaded without the page query var.
		if ( '' === $page && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && isset( $screen->id ) && in_array(
				$screen->id,
				array( 'settings_page_' . self::SLUG, 'toplevel_page_' . self::SLUG ),
				true
			) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The sanitized ?view= request value, limited to the two known views. Empty
	 * string when absent or invalid.
	 */
	private static function requested_view(): string {
		if ( ! isset( $_GET['view'] ) ) {
			return '';
		}
		$view = sanitize_key( wp_unslash( $_GET['view'] ) );

		return in_array( $view, array( 'new', 'classic' ), true ) ? $view : '';
	}

	/**
	 * The stored per-user view preference, or empty string when none/invalid.
	 */
	private static function stored_view(): string {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		$view = get_user_meta( $user_id, self::VIEW_META, true );

		return in_array( $view, array( 'new', 'classic' ), true ) ? (string) $view : '';
	}

	/**
	 * Persist the view preference — but only for a user with the page capability
	 * and only when the switch nonce is valid. Without the nonce the ?view=
	 * value still applies for the request (see current_view) but is not stored.
	 */
	private static function maybe_persist_view( string $view ): void {
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::VIEW_NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		update_user_meta( $user_id, self::VIEW_META, $view );
	}

	/**
	 * A self-persisting switcher URL for the given target view (adds ?view= and
	 * the switch nonce to the current admin URL, preserving other params).
	 */
	private static function switch_url( string $target ): string {
		return esc_url(
			add_query_arg(
				array(
					'view'     => $target,
					'_wpnonce' => wp_create_nonce( self::VIEW_NONCE ),
				)
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Menu wiring — no own submenu; hook the merged screen's load actions.
	 * ------------------------------------------------------------------- */

	public function add_menu(): void {
		if ( ! self::is_supported() ) {
			return;
		}

		// The classic admin registers this slug as an options page (single-site,
		// hook settings_page_ip-location-block) and a network top-level page
		// (hook toplevel_page_ip-location-block). Attach to both known load
		// hooks; only the one for the current context ever fires.
		foreach ( array( 'settings_page_' . self::SLUG, 'toplevel_page_' . self::SLUG ) as $hook ) {
			add_action( 'load-' . $hook, array( $this, 'on_load' ) );
		}
	}

	/**
	 * Fires when the merged screen loads. Enqueues the React bundle under the
	 * new view; otherwise wires the additive classic-view switcher + bar.
	 */
	public function on_load(): void {
		if ( self::is_react_screen() ) {
			$this->enqueue();

			return;
		}

		// Classic view of the merged page: additive deprecation UI only. No
		// React, no changes to the frozen admin file.
		$this->enqueue_switcher_style();
		add_action( 'all_admin_notices', array( $this, 'render_deprecation_bar' ) );
		add_action( 'admin_footer', array( $this, 'render_view_switcher' ) );
	}

	/**
	 * Redirect pre-1.4.0 opt-in-slug URLs to the merged page, preserving the
	 * deep-link params that both UIs understand. Runs on admin_menu (see the
	 * constructor) so it fires before core's access-denied 403.
	 */
	public function maybe_redirect_legacy_slug(): void {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		if ( self::LEGACY_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$args = array( 'page' => self::SLUG );
		foreach ( array( 'tab', 'view', 's', 'section', 'sec' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		$base = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'options-general.php' );
		wp_safe_redirect( add_query_arg( $args, $base ) );
		exit;
	}

	/**
	 * Enqueue the built React bundle + its WP script dependencies (read from
	 * the generated asset manifest) and bootstrap data for the app.
	 */
	public function enqueue(): void {
		$asset_path = IP_LOCATION_BLOCK_PATH . 'admin/app/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return; // assets not built (npm run build)
		}
		$asset = require $asset_path;

		// Bundled Leaflet for the Search tab map (exposes window.L).
		wp_enqueue_style(
			'ip-location-block-leaflet',
			plugins_url( 'admin/app/vendor/leaflet/leaflet.css', IP_LOCATION_BLOCK_BASE ),
			array(),
			IP_LOCATION_BLOCK_VERSION
		);
		wp_enqueue_script(
			'ip-location-block-leaflet',
			plugins_url( 'admin/app/vendor/leaflet/leaflet.js', IP_LOCATION_BLOCK_BASE ),
			array(),
			IP_LOCATION_BLOCK_VERSION,
			true
		);

		wp_enqueue_script(
			self::SLUG,
			plugins_url( 'admin/app/build/index.js', IP_LOCATION_BLOCK_BASE ),
			array_merge( $asset['dependencies'], array( 'ip-location-block-leaflet' ) ),
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'dashicons' );

		// @wordpress/scripts splits extracted CSS into two files: shared styles
		// from any `style.scss` land in style-index.css, while styles imported
		// from other-named modules (e.g. charts.scss) land in index.css. Enqueue
		// both so every component's styles load.
		wp_enqueue_style(
			self::SLUG,
			plugins_url( 'admin/app/build/style-index.css', IP_LOCATION_BLOCK_BASE ),
			array( 'wp-components' ),
			$asset['version']
		);
		if ( file_exists( IP_LOCATION_BLOCK_PATH . 'admin/app/build/index.css' ) ) {
			wp_enqueue_style(
				self::SLUG . '-components',
				plugins_url( 'admin/app/build/index.css', IP_LOCATION_BLOCK_BASE ),
				array( self::SLUG ),
				$asset['version']
			);
		}

		// Shared switcher/deprecation-bar styles, so the React-side floating
		// switcher matches the classic-side one pixel-for-pixel.
		$this->enqueue_switcher_style();

		wp_localize_script( self::SLUG, 'ipLocationBlockAdmin', array(
			'restNamespace'   => 'ip-location-block/v1',
			'restRoot'        => esc_url_raw( rest_url() ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'isNetwork'       => is_network_admin(),
			'version'         => IP_LOCATION_BLOCK_VERSION,
			'logoUrl'         => plugins_url( 'admin/images/logo.svg', IP_LOCATION_BLOCK_BASE ),
			'docsUrl'         => 'https://iplocationblock.com/codex/?utm_source=plugin&utm_medium=admin&utm_campaign=admin_topbar',
			'viewSwitchNonce' => wp_create_nonce( self::VIEW_NONCE ),
		) );

		wp_set_script_translations( self::SLUG, 'ip-location-block' );
	}

	/**
	 * The small, dependency-free stylesheet shared by the React-side floating
	 * switcher and the classic-side switcher + deprecation bar.
	 */
	private function enqueue_switcher_style(): void {
		wp_enqueue_style(
			'ip-location-block-view-switcher',
			plugins_url( 'admin/app/view-switcher.css', IP_LOCATION_BLOCK_BASE ),
			array( 'dashicons' ),
			IP_LOCATION_BLOCK_VERSION
		);
	}

	/**
	 * Slim deprecation bar shown above the classic content (classic view only).
	 */
	public function render_deprecation_bar(): void {
		echo '<div class="ilb-deprecation-bar">';
		echo '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>';
		echo '<span class="ilb-deprecation-bar__text">';
		echo esc_html__( 'You are viewing the classic interface. It will be removed in a future major release.', 'ip-location-block' );
		echo '</span>';
		echo '<a class="ilb-deprecation-bar__link" href="' . self::switch_url( 'new' ) . '">';
		echo esc_html__( 'Switch to the new interface', 'ip-location-block' );
		echo '</a>';
		echo '</div>';
	}

	/**
	 * Floating edge switcher back to the new interface (classic view only). Uses
	 * the same markup/classes as the React-side ViewSwitcher so both feel like
	 * one control.
	 */
	public function render_view_switcher(): void {
		echo '<a class="ilb-view-switcher ilb-view-switcher--to-new" href="' . self::switch_url( 'new' ) . '" title="'
			. esc_attr__( 'Switch to the new interface', 'ip-location-block' ) . '">';
		echo '<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>';
		echo '<span class="ilb-view-switcher__label">' . esc_html__( 'New interface', 'ip-location-block' ) . '</span>';
		echo '</a>';
	}

	public function render(): void {
		// Give WordPress a definite notice anchor before the React heading. This
		// screen's stylesheet suppresses those notices, but the marker prevents
		// core or third-party code from relocating them inside the product bar.
		// The ilb-view-new class scopes notice suppression to the React view so
		// the classic view keeps normal WordPress notices.
		echo '<div class="wrap ilb-view-new"><hr class="wp-header-end" /><div id="ip-location-block-app"></div></div>';
	}
}
