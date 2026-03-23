<?php
/* @var array $settings */

$providers = IP_Location_Block_Provider::get_valid_providers($settings);
$uses_api  = in_array('IP Location Block',  $providers);

$quota = null;
$is_rate_limited = false;
$requires_key_upgrade = false;
$api_key = !empty($settings['providers']['IP Location Block']) ? $settings['providers']['IP Location Block'] : '';

if($uses_api && !empty($api_key)) {
	$quota = IP_Location_Block_Provider::get_native_quota($api_key);

	// Check if we got a rate limit response
	if (isset($quota['error']) && isset($quota['status']) && $quota['status'] === 'rate_limited') {
		$is_rate_limited = true;
	}

	// Check if API key needs to be upgraded (rehashed due to encryption changes)
	if (isset($quota['name']) && $quota['name'] === 'requires-api-key-upgrade') {
		$requires_key_upgrade = true;
	}
}
$is_unlimited = isset($quota['balance']['recurring']) && $quota['balance']['recurring'] === -1;
$is_balance_zero = !isset($quota['balance']['total']) || (int) $quota['balance']['total'] === 0;

?>
<div class="ip-location-block-meta-wrapper">
	<?php if ( $uses_api ): ?>
        <?php
		$is_native = IP_Location_Block_Provider::is_native($settings);
        if($requires_key_upgrade || $is_rate_limited || $is_balance_zero) {
            $signal_css = 'native-mode-error';
        } else if($is_native) {
            $signal_css = 'native-mode-ok';
        } else {
	        $signal_css = 'standard-mode';
        }

		$provider_name = isset($quota['name']) && $quota['name'] !== 'requires-api-key-upgrade' ? $quota['name'] : 'IP Location Block';
		?>
		<div class="ip-location-block-provider-meta <?php echo esc_attr($signal_css); ?>">
			<span class="ip-location-block-sign" title="Native mode gives better precision and city/state level blocking"></span>
			<span class="ip-location-block-name"><?php echo sprintf( '<strong>%s</strong> %s', $provider_name, '(<em>' . ($is_native ? __( 'Native Mode', 'ip-location-block' ) : __( 'Standard Mode', 'ip-location-block' )) .'</em>)'); ?></span>
			<span class="dashicons dashicons-arrow-down"></span>
		</div>
		<div id="subscription-<?php echo (int) isset( $quota['subscription']['id'] ) ? $quota['subscription']['id'] : 0; ?>"
		     class="ip-location-block-provider-meta-details">
			<?php if ( ! $requires_key_upgrade ): ?>
				<?php if ( ! empty( $quota['subscription']['plan_name'] ) ): ?>
					<div class="ip-location-block-provider-meta-row">
						<div class="ip-location-block-provider-meta-plan">
							<?php echo esc_html( $quota['subscription']['plan_name'] ); ?>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( isset( $quota['balance']['recurring'] ) && isset( $quota['subscription']['tokens'] ) ): ?>
					<div class="ip-location-block-provider-meta-row">
						<div class="ip-location-block-provider-meta-col1"><?php _e( 'Recurring Balance', 'ip-location-block' ); ?></div>
						<div class="ip-location-block-provider-meta-col2"><?php echo $is_unlimited ? __( 'Unlimited', 'ip-location-block' ) : sprintf( '%d / %d', (int) $quota['balance']['recurring'], (int) $quota['subscription']['tokens'] ); ?></div>
					</div>
				<?php endif; ?>
				<?php if ( ! $is_unlimited ): ?>
					<?php if ( isset( $quota['balance']['onetime'] ) ): ?>
						<div class="ip-location-block-provider-meta-row">
							<div class="ip-location-block-provider-meta-col1"><?php _e( 'Onetime Balance', 'ip-location-block' ); ?></div>
							<div class="ip-location-block-provider-meta-col2"><?php echo (int) $quota['balance']['onetime']; ?></div>
						</div>
					<?php endif; ?>
					<?php if ( isset( $quota['balance']['total'] ) ): ?>
						<div class="ip-location-block-provider-meta-row">
							<div class="ip-location-block-provider-meta-col1"><?php _e( 'Total Balance', 'ip-location-block' ); ?></div>
							<div class="ip-location-block-provider-meta-col2"><?php echo (int) $quota['balance']['total']; ?></div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $requires_key_upgrade ): ?>
				<div class="ip-location-block-provider-meta-row">
					<div class="ip-location-block-provider-meta-account">
						<p>
							<strong><?php _e( 'API Key Upgrade Required', 'ip-location-block' ); ?>:</strong>
							<?php _e( 'Your API key needs to be upgraded due to security improvements. Please submit the form below to upgrade your key. This is a one-time process and will only take a moment.', 'ip-location-block' ); ?>
						</p>
						<?php if ( ! empty( $api_key ) ): ?>
							<p>
								<a target="_blank" class="button button-primary button-small"
								   href="<?php echo esc_url( 'https://app.iplocationblock.com/upgrade-api-key?api_key=' . urlencode( $api_key ) ); ?>"><?php _e( 'Upgrade API Key', 'ip-location-block' ); ?></a>
							</p>
						<?php else: ?>
							<p>
								<a target="_blank" class="button button-primary button-small"
								   href="https://app.iplocationblock.com/upgrade-api-key"><?php _e( 'Upgrade API Key', 'ip-location-block' ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php elseif ( $is_rate_limited ): ?>
				<div class="ip-location-block-provider-meta-row">
					<div class="ip-location-block-provider-meta-account">
						<p>
							<strong><?php _e( 'Rate Limit Reached', 'ip-location-block' ); ?>:</strong>
							<?php
							if ( isset( $quota['error'] ) ) {
								echo esc_html( $quota['error'] );
							} else {
								_e( 'You have reached your API rate limit. Please upgrade your plan to continue using IP Location Block.', 'ip-location-block' );
							}
							?>
						</p>
						<?php if ( ! empty( $api_key ) ): ?>
							<p>
								<a target="_blank" class="button button-primary button-small"
								   href="<?php echo esc_url( 'https://app.iplocationblock.com/upgrade-api-key?api_key=' . urlencode( $api_key ) ); ?>"><?php _e( 'Upgrade Your Plan', 'ip-location-block' ); ?></a>
							</p>
						<?php else: ?>
							<p>
								<a target="_blank" class="button button-primary button-small"
								   href="https://app.iplocationblock.com/billing/plans?utm_source=wordpress&utm_medium=site&utm_campaign=cloud"><?php _e( 'Upgrade Your Plan', 'ip-location-block' ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php elseif ( $is_balance_zero ): ?>
				<div class="ip-location-block-provider-meta-row">
					<div class="ip-location-block-provider-meta-account">
						<p>
							<strong><?php _e( 'Attention', 'ip-location-block' ); ?>
								:</strong> <?php _e( 'Looks like your account is out of balance! IP Location Block will NOT WORK as long as the balance is zero.', 'ip-location-block' ); ?>
						</p>
						<p>
							<a target="_blank" class="button button-primary button-small"
							   href="https://iplocationblock.com/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud"><?php _e( 'Upgrade', 'ip-location-block' ); ?></a>
						</p>
					</div>
				</div>
			<?php else: ?>
				<div class="ip-location-block-provider-meta-row">
					<div class="ip-location-block-provider-meta-account">
						<a target="_blank" class="button button-primary button-small"
						   href="https://app.iplocationblock.com/login"><?php _e( 'My Account', 'ip-location-block' ); ?></a>
					</div>
				</div>
			<?php endif; ?>
			<?php if(!$is_native): ?>
                <div class="ip-location-block-provider-meta-row">
                    <div class="ip-location-block-provider-meta-attention">
                        <p>
							<?php _e( 'You are running in <strong>Standard Mode</strong>, precision blocking by state/city will not work. To enable <strong>Native Mode</strong> disable the following providers:', 'ip-location-block' ); ?>
                        </p>
                        <p>
							<?php
							echo implode( ', ', array_map( function ( $item ) {
								return '<em>' . $item . '</em>';
							}, IP_Location_Block_Util::array_except( $providers, [
								'IP Location Block',
								'Cache'
							] ) ) );
							?>
                        </p>
                    </div>
                </div>
			<?php endif; ?>
		</div>
	<?php else: ?>
		<div class="ip-location-block-provider-meta standard-mode">
            <span class="ip-location-block-sign" title="<?php _e('Use standard mode if you don\'t want improved precision or fast support.', 'ip-location-block'); ?>"></span>
			<span class="ip-location-block-name"><?php _e( 'Standard Mode', 'ip-location-block' ); ?></span>
			<span class="dashicons dashicons-arrow-down"></span>
		</div>
		<div class="ip-location-block-provider-meta-details">
			<p><strong><?php _e('Standard Mode', 'ip-location-block'); ?></strong></p>
			<ul>
				<li><?php _e('Country blocking', 'ip-location-block'); ?></li>
				<li><?php _e('Normal data precision', 'ip-location-block'); ?></li>
				<li><?php _e('Normal support', 'ip-location-block'); ?><br/><em><?php _e('1-3 day response', 'ip-location-block'); ?></em></li>
			</ul>
			<p><strong><?php _e('Native Mode', 'ip-location-block'); ?></strong></p>
			<ul>
				<li><?php _e('Country, city, state blocking', 'ip-location-block'); ?> & <a href="https://iplocationblock.com/codex/supported-geo-location-rule-formats/" target="_blank"><?php _e('Advanced patterns', 'ip-location-block'); ?></a></li>
				<li><?php _e('Improved data precision', 'ip-location-block'); ?></li>
				<li><?php _e('Priority support', 'ip-location-block'); ?><br/><em><?php _e('1-5 hr response', 'ip-location-block'); ?></em></li>
			</ul>
			<div class="ip-location-block-provider-meta-upgrade">
				<div class="ip-location-block-provider-meta-account">
					<p>
						<?php _e('To upgrade to <strong>Native Mode</strong>, please sign up for a key and set up the "IP Location Block" provider
						in the settings.', 'ip-location-block'); ?>
					</p>
					<p>
						<?php _e('Make sure to also <strong>disable</strong> the other providers as well.', 'ip-location-block'); ?>
					</p>
					<p class="ilb-text-center">
						<a target="_blank" class="button button-primary button-small" href="https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud"><?php _e( 'Upgrade', 'ip-location-block' ); ?></a>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>