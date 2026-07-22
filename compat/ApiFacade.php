<?php
/**
 * Legacy geolocation-API facade.
 *
 * `abstract class IP_Location_Block_API` — keeps the legacy static entry points
 * (get_instance / get_class_name / get_api_key) working against the new sealed
 * ProviderRegistry, and preserves the historic protected surface so third-party
 * subclasses (`class My_Geo extends IP_Location_Block_API`) still parse. Those
 * subclasses are never instantiated by the pipeline — the sealed registry makes
 * them inert (register_addon() is a deprecated no-op).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

abstract class IP_Location_Block_API {

	/* --- inert protected surface (parse-compat for external subclasses) --- */

	/** @var string */
	protected $provider = '';

	/** @var array */
	protected $options = array();

	/** @var array */
	protected $supports = array();

	/** @var array */
	protected $template = array();

	/**
	 * Legacy protected constructor. Never reached for registry providers.
	 *
	 * @param string $provider
	 * @param array  $options
	 */
	protected function __construct( $provider, $options = array() ) {
		$this->provider = $provider;
		$this->options  = $options;
	}

	/**
	 * Legacy build_url() — kept so subclasses calling parent::build_url() parse.
	 *
	 * @return string|string[]
	 */
	protected static function build_url( $ip, $template ) {
		$template['api']['%API_IP%'] = $ip;

		return str_replace(
			array_keys( $template['api'] ),
			array_values( $template['api'] ),
			$template['url']
		);
	}

	/**
	 * Inert fetch — real HTTP lookups now run through the new providers.
	 *
	 * @return array
	 */
	protected function fetch_provider( $ip, $args ) {
		return array();
	}

	/**
	 * @return array|false|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		return $this->fetch_provider( $ip, $args );
	}

	/**
	 * @return false|mixed|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		$res = $this->get_location( $ip, $args );

		return false === $res ? false : ( empty( $res['countryCode'] ) ? null : $res['countryCode'] );
	}

	/**
	 * @return mixed
	 */
	protected static function post_process( $processed, $original ) {
		return $processed;
	}

	/**
	 * @param string $feature
	 *
	 * @return bool
	 */
	public function supports( $feature ) {
		return is_array( $this->supports ) && in_array( $feature, $this->supports );
	}

	/* --- live static API -------------------------------------------------- */

	/**
	 * Resolve a provider name to its canonical registry id (applying the legacy
	 * 'Maxmind' => 'GeoLite2' alias), or null when unknown.
	 *
	 * NOTE: this now returns the canonical PROVIDER ID, not a PHP class name —
	 * the per-provider IP_Location_Block_API_* subclasses no longer exist. No
	 * in-tree caller relied on the class-name form; get_instance() is the
	 * supported dispatch.
	 *
	 * @param string $provider
	 *
	 * @return string|null
	 */
	public static function get_class_name( $provider ) {
		if ( 'Maxmind' === $provider ) {
			$provider = 'GeoLite2';
		}
		if ( 'Cache' === $provider ) {
			return 'Cache';
		}

		return \IPLocationBlock\Providers\ProviderRegistry::instance()->has( $provider ) ? $provider : null;
	}

	/**
	 * Resolve the stored credential for a provider.
	 *
	 * @return mixed|null
	 */
	public static function get_api_key( $provider, $options ) {
		if ( empty( $provider ) || empty( $options['providers'] ) ) {
			return null;
		}

		$providers = array();
		if ( ! empty( $options['providers'] ) && is_array( $options['providers'] ) ) {
			$providers = array_change_key_case( $options['providers'], CASE_LOWER );
		}
		$provider = strtolower( $provider );

		return empty( $providers[ $provider ] ) ? null : $providers[ $provider ];
	}

	/**
	 * Return a legacy geo object for a provider name:
	 *   - a LegacyLocalProviderAdapter for local providers,
	 *   - a LegacyProviderAdapter for remote providers,
	 *   - the synthetic Cache adapter for 'Cache',
	 *   - null for unknown / removed names ('GeoIPLookup', 'Ipdataco', …).
	 *
	 * @return object|null
	 */
	public static function get_instance( $provider, $options ) {
		$name = ( 'Maxmind' === $provider ) ? 'GeoLite2' : $provider;

		if ( 'Cache' === $name ) {
			return IP_Location_Block_API_Cache::adapter();
		}

		$registry = \IPLocationBlock\Providers\ProviderRegistry::instance();
		$instance = $registry->get( $name );
		if ( ! $instance ) {
			return null;
		}

		$opts = is_array( $options ) ? $options : array();

		return $instance->isLocal()
			? new \IPLocationBlock\Compat\LegacyLocalProviderAdapter( $instance, $opts )
			: new \IPLocationBlock\Compat\LegacyProviderAdapter( $instance, $opts );
	}
}
