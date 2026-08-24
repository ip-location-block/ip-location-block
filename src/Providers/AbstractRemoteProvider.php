<?php
/**
 * Base class for remote (HTTP) geolocation providers.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

use IPLocationBlock\Geolocation\LocationResult;

/**
 * Shared HTTP-provider engine: placeholder substitution, wp_remote_get,
 * content-type sniffing (json/xml/plain), transform map, short-term wp_cache
 * and IP-family rejection.
 */
abstract class AbstractRemoteProvider implements ProviderInterface {

	protected const CACHE_GROUP = 'ip-location-block';

	/**
	 * URL template with %API_IP% / %API_KEY% / %API_FORMAT% / %API_OPTION%.
	 */
	abstract protected function urlTemplate(): string;

	/**
	 * transform map: LocationResult key => provider response key.
	 *
	 * @return array<string,string>
	 */
	abstract protected function transformMap(): array;

	/**
	 * Default %API_* placeholder values.
	 *
	 * @return array<string,string>
	 */
	protected function apiDefaults(): array {
		return array();
	}

	public function isLocal(): bool {
		return false;
	}

	public function implicitlyEnabled(): bool {
		return false;
	}

	public function limits(): ?array {
		return null;
	}

	public function legacySupports(): array {
		return Capability::toLegacyList( $this->capabilities() );
	}

	public function lookup( string $ip, LookupContext $context ): LocationResult {
		$raw = $this->fetch( $ip, $context, $this->apiDefaults() );

		return $this->wrap( $raw );
	}

	public function lookupCountry( string $ip, LookupContext $context ): LocationResult {
		return $this->lookup( $ip, $context );
	}

	/**
	 * Provider-specific post-processing of the raw transform array. The base
	 * implementation is the identity mapping. Overridden by providers that need
	 * to reshape the response further (ipinfo.io, ipapi).
	 *
	 * @param array<string,mixed> $raw
	 */
	protected function mapResult( array $raw ): LocationResult {
		return LocationResult::fromArray( $raw );
	}

	/**
	 * Convert the raw fetch() return (array|false) into a LocationResult.
	 *
	 * @param array<string,mixed>|false $raw
	 */
	protected function wrap( array|false $raw ): LocationResult {
		if ( false === $raw ) {
			return LocationResult::rejected();
		}
		if ( isset( $raw['errorMessage'] ) && '' !== $raw['errorMessage'] ) {
			return LocationResult::error( (string) $raw['errorMessage'] );
		}

		return $this->mapResult( $raw );
	}

	/**
	 * Fetch and normalize a provider's HTTP response into the transform array.
	 *
	 * @param array<string,string> $api Placeholder overrides for this call.
	 *
	 * @return array<string,mixed>|false Legacy transform array, an errorMessage
	 *                                   array, or false for IP-family rejection.
	 */
	protected function fetch( string $ip, LookupContext $context, array $api ) {
		$credential = $context->credentialFor( $this->id() );
		if ( is_string( $credential ) ) {
			$api['%API_KEY%'] = $credential;
		}

		$url        = $this->urlTemplate();
		$cache_key  = md5( $ip . $url );
		$cache_grp  = self::CACHE_GROUP;

		if ( ! $context->fresh ) {
			$cache = wp_cache_get( $cache_key, $cache_grp );
			if ( false !== $cache ) {
				return is_array( $cache ) ? $cache : false;
			}
		}

		// Reject IP families the provider cannot answer.
		if ( ! Capability::supportsIp( $this->capabilities(), $ip ) ) {
			return false;
		}

		// build_url(): substitute placeholders.
		$api['%API_IP%'] = $ip;
		$request_url     = str_replace( array_keys( $api ), array_values( $api ), $url );

		$res = wp_remote_get( $request_url, $context->httpArgs() );
		if ( is_wp_error( $res ) ) {
			return array( 'errorMessage' => $res->get_error_message() );
		}

		$ctype = wp_remote_retrieve_header( $res, 'content-type' );
		$body  = wp_remote_retrieve_body( $res );
		$data  = array();

		// Extract the content type, e.g. "text/plain; charset=utf-8" => "plain".
		if ( $ctype ) {
			$ctype = explode( '/', $ctype, 2 );
			$ctype = explode( ';', $ctype[1] ?? '', 2 );
			$ctype = trim( $ctype[0] );
		}

		switch ( $ctype ) {
			case 'json':
			case 'html':  // ipinfo.io, Xhanch
			case 'plain': // geoPlugin
				$data = json_decode( $body, true );
				if ( null === $data ) { // ipinfo.io get_country returns bare text
					$data   = array();
					$cc_key = $this->transformMap()['countryCode'] ?? '';
					if ( '' !== $cc_key ) {
						$data[ $cc_key ] = trim( $body );
					}
				}
				break;

			case 'xml':
				$pattern = '/\<(.+?)\>(?:\<\!\[CDATA\[)?([^\>]*?)(?:\]\]\>)?\<\/\\1\>/i';
				if ( preg_match_all( $pattern, $body, $matches ) !== false ) {
					if ( is_array( $matches[1] ) && ! empty( $matches[1] ) ) {
						foreach ( $matches[1] as $key => $val ) {
							$data[ $val ] = $matches[2][ $key ];
						}
					}
				}
				break;

			default:
				return array( 'errorMessage' => "unsupported content type: $ctype" );
		}

		// Transformation.
		$out = array();
		foreach ( $this->transformMap() as $key => $val ) {
			if ( ! empty( $val ) && ! empty( $data[ $val ] ) ) {
				$out[ $key ] = is_string( $data[ $val ] ) ? esc_html( $data[ $val ] ) : $data[ $val ];
			}
		}

		if ( isset( $out['countryCode'] ) && is_string( $out['countryCode'] ) ) {
			$out['countryCode'] = LocationResult::normalizeCountryCode( $out['countryCode'] );
		}

		if ( ! $context->fresh ) {
			wp_cache_set( $cache_key, $out, $cache_grp );
		}

		return $out;
	}
}
