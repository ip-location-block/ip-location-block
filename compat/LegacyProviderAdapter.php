<?php
/**
 * Legacy remote-provider adapter.
 *
 * Wraps a new ProviderInterface behind the legacy geo-object surface returned
 * by IP_Location_Block_API::get_instance(): get_location() / get_country() /
 * supports(). Duck-typed — callers never reference the class by name.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

namespace IPLocationBlock\Compat;

use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\ProviderInterface;

class LegacyProviderAdapter {

	/** @var ProviderInterface */
	protected $provider;

	/** @var array */
	protected $options;

	public function __construct( ProviderInterface $provider, $options ) {
		$this->provider = $provider;
		$this->options  = is_array( $options ) ? $options : array();
	}

	/**
	 * Build a per-call LookupContext from legacy $args (may carry fresh/asn).
	 *
	 * @param mixed $args
	 */
	protected function context( $args ): LookupContext {
		return LookupContext::fromLegacyArgs( $this->options, is_array( $args ) ? $args : array() );
	}

	/**
	 * Legacy get_location(): the transform array, or false for IP-family
	 * rejection, or an errorMessage array.
	 *
	 * @return array|false
	 */
	public function get_location( $ip, $args = array() ) {
		$result = $this->provider->lookup( (string) $ip, $this->context( $args ) );
		if ( $result->isRejected() ) {
			return false;
		}

		return $result->toLegacyArray();
	}

	/**
	 * Legacy get_country(): false for rejection, null for no code, else the code.
	 *
	 * @return string|false|null
	 */
	public function get_country( $ip, $args = array() ) {
		$result = $this->provider->lookupCountry( (string) $ip, $this->context( $args ) );
		if ( $result->isRejected() ) {
			return false;
		}
		$arr = $result->toLegacyArray();

		return empty( $arr['countryCode'] ) ? null : $arr['countryCode'];
	}

	/**
	 * Legacy supports() — scalar feature check.
	 *
	 * @param string $feature
	 *
	 * @return bool
	 */
	public function supports( $feature ) {
		return in_array( $feature, $this->provider->legacySupports() );
	}
}
