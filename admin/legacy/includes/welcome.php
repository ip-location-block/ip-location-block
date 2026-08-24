<?php
/**
 * Welcome panel shown after installation until the current campaign is closed.
 *
 * @package IP_Location_Block
 */

$url_docs      = 'https://iplocationblock.com/docs/getting-started/?utm_source=plugin&utm_medium=welcome&utm_campaign=documentation';
$url_purchase  = 'https://iplocationblock.com/pricing/?utm_source=plugin&utm_medium=welcome&utm_campaign=regional_rules';
$url_github    = 'https://github.com/ip-location-block/ip-location-block/';
$url_wordpress = 'https://wordpress.org/support/plugin/ip-location-block/';
$url_review    = 'https://wordpress.org/support/plugin/ip-location-block/reviews/#new-post';
$logo          = plugins_url( 'admin/images/logo.svg', IP_LOCATION_BLOCK_BASE );

$admin_page = is_network_admin()
	? network_admin_url( 'admin.php' )
	: admin_url( 'options-general.php' );

$url_provider = add_query_arg(
	array(
		'page' => 'ip-location-block',
		'tab'  => 'settings',
		'view' => 'simple',
	),
	$admin_page
) . '#ilb-provider-setup';
$url_locations = add_query_arg(
	array(
		'page' => 'ip-location-block',
		'tab'  => 'settings',
		'view' => 'simple',
	),
	$admin_page
) . '#ilb-location-blocking';
$url_search = add_query_arg(
	array(
		'page' => 'ip-location-block',
		'tab'  => 'search',
	),
	$admin_page
);
?>

<div class="ilb-welcome">
	<div class="ilb-welcome__hero">
		<img class="ilb-welcome__logo" src="<?php echo esc_url( $logo ); ?>" alt="" width="48" height="48" />
		<div class="ilb-welcome__intro">
			<h2 class="ilb-welcome__title">
				<?php esc_html_e( 'Welcome to IP Location Block', 'ip-location-block' ); ?>
				<span class="ilb-welcome__version">v<?php echo esc_html( IP_LOCATION_BLOCK_VERSION ); ?></span>
			</h2>
			<p class="ilb-welcome__desc">
				<?php esc_html_e( 'Block unwanted visitors by country, or add state or region precision when you need finer control.', 'ip-location-block' ); ?>
			</p>
		</div>
	</div>

	<div class="ilb-welcome__choices">
		<section class="ilb-welcome__choice ilb-welcome__choice--recommended" aria-labelledby="ilb-welcome-regional-title">
			<div class="ilb-welcome__choice-heading">
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
				<h3 id="ilb-welcome-regional-title"><?php esc_html_e( 'State or region blocking', 'ip-location-block' ); ?></h3>
				<span class="ilb-welcome__badge"><?php esc_html_e( 'Recommended', 'ip-location-block' ); ?></span>
			</div>
			<ul class="ilb-welcome__benefits">
				<li><?php esc_html_e( 'Block states, provinces, prefectures, or territories', 'ip-location-block' ); ?></li>
				<li><?php esc_html_e( 'IPv6 and ASN lookups included', 'ip-location-block' ); ?></li>
				<li><?php esc_html_e( 'Managed provider built for this plugin', 'ip-location-block' ); ?></li>
			</ul>
			<div class="ilb-welcome__choice-actions">
				<a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $url_purchase ); ?>">
					<?php esc_html_e( 'See regional plans', 'ip-location-block' ); ?>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $url_provider ); ?>"><?php esc_html_e( 'I already have a key', 'ip-location-block' ); ?></a>
			</div>
		</section>

		<section class="ilb-welcome__choice" aria-labelledby="ilb-welcome-country-title">
			<div class="ilb-welcome__choice-heading">
				<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
				<h3 id="ilb-welcome-country-title"><?php esc_html_e( 'Country-level blocking', 'ip-location-block' ); ?></h3>
			</div>
			<ul class="ilb-welcome__benefits">
				<li><?php esc_html_e( 'Block or allow selected countries', 'ip-location-block' ); ?></li>
				<li><?php esc_html_e( 'Use a local database or API provider', 'ip-location-block' ); ?></li>
				<li><?php esc_html_e( 'No regional plan required', 'ip-location-block' ); ?></li>
			</ul>
			<div class="ilb-welcome__choice-actions">
				<a class="button" href="<?php echo esc_url( $url_provider ); ?>">
					<?php esc_html_e( 'Set up country blocking', 'ip-location-block' ); ?>
				</a>
			</div>
		</section>
	</div>

	<div class="ilb-welcome__lower">
		<nav class="ilb-welcome__steps" aria-label="<?php esc_attr_e( 'Getting started', 'ip-location-block' ); ?>">
			<span class="ilb-welcome__steps-label"><?php esc_html_e( 'Get protected:', 'ip-location-block' ); ?></span>
			<a href="<?php echo esc_url( $url_provider ); ?>"><span>1</span><?php esc_html_e( 'Choose a provider', 'ip-location-block' ); ?></a>
			<span class="ilb-welcome__step-arrow" aria-hidden="true">&#8594;</span>
			<a href="<?php echo esc_url( $url_locations ); ?>"><span>2</span><?php esc_html_e( 'Select locations', 'ip-location-block' ); ?></a>
			<span class="ilb-welcome__step-arrow" aria-hidden="true">&#8594;</span>
			<a href="<?php echo esc_url( $url_search ); ?>"><span>3</span><?php esc_html_e( 'Verify an IP', 'ip-location-block' ); ?></a>
		</nav>

		<div class="ilb-welcome__links">
			<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $url_docs ); ?>"><?php esc_html_e( 'Documentation', 'ip-location-block' ); ?></a>
			<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $url_wordpress ); ?>"><?php esc_html_e( 'Support', 'ip-location-block' ); ?></a>
			<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $url_github ); ?>"><?php esc_html_e( 'GitHub', 'ip-location-block' ); ?></a>
			<a class="ilb-welcome__rate" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Give this plugin a five star rating', 'ip-location-block' ); ?>" href="<?php echo esc_url( $url_review ); ?>">
				<span aria-hidden="true">&#9733;</span><?php esc_html_e( 'Rate this plugin', 'ip-location-block' ); ?>
			</a>
		</div>
	</div>
</div>
