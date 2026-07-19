<?php
/**
 * GeoLite2 (MaxMind) local provider.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers\Local;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Providers\AbstractLocalProvider;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\DownloadReport;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\ProviderRegistry;
use IPLocationBlock\Support\FileSystem;
use IPLocationBlock\Support\Util;
use IPLocationBlock\Vendor\GeoIp2\Database\Reader;

/**
 * Lookups run against the SCOPED GeoIp2 reader; the separate ASN-database
 * second pass is preserved via LookupContext's asnPass flag. Every
 * `ip-location-block-geolite2-*` filter is preserved at its firing point.
 */
final class GeoLite2Provider extends AbstractLocalProvider {

	public const DB_IP    = 'GeoLite2-Country.mmdb';
	public const DB_ASN   = 'GeoLite2-ASN.mmdb';
	public const ZIP_IP   = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz';
	public const ZIP_ASN  = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-ASN&license_key=%s&suffix=tar.gz';
	public const DOWNLOAD_PAGE = 'https://dev.maxmind.com/geoip/geoip2/geolite2/';

	public function id(): string {
		return 'GeoLite2';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / Apache License, Version 2.0';
	}

	public function link(): ?string {
		return 'https://dev.maxmind.com/geoip/geolite2-free-geolocation-data';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6 | Capability::ASN | Capability::ASN_DATABASE;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_REQUIRED;
	}

	public function implicitlyEnabled(): bool {
		return false;
	}

	public function limits(): ?array {
		return array( 'System memory' );
	}

	private function dbDir(): string {
		return apply_filters( 'ip-location-block-geolite2-dir', $this->storageDir() );
	}

	public function lookup( string $ip, LookupContext $context ): LocationResult {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return LocationResult::error( 'illegal format' );
		}

		$settings = $context->settings;

		if ( ! $context->asnPass ) {
			$file = apply_filters(
				'ip-location-block-geolite2-path',
				! empty( $settings['GeoLite2']['ip_path'] )
					? $settings['GeoLite2']['ip_path']
					: $this->dbDir() . self::DB_IP
			);
			try {
				$reader = new Reader( $file );
				if ( 'GeoLite2-Country' === $reader->metadata()->databaseType ) {
					$record = $reader->country( $ip );
					$res    = array( 'countryCode' => $record->country->isoCode );
				} else {
					$record = $reader->city( $ip );
					$res    = array(
						'countryCode' => $record->country->isoCode,
						'countryName' => $record->country->names['en'],
						'cityName'    => $record->city->names['en'],
						'latitude'    => $record->location->latitude,
						'longitude'   => $record->location->longitude,
					);
				}
			} catch ( \Exception $e ) {
				$res = array( 'countryCode' => null );
			}
		} else {
			$file = ! empty( $settings['GeoLite2']['asn_path'] )
				? $settings['GeoLite2']['asn_path']
				: $this->dbDir() . self::DB_ASN;
			try {
				$reader = new Reader( $file );
				$res    = array( 'asn' => Util::parse_asn( $reader->asn( $ip )->autonomousSystemNumber ) );
			} catch ( \Exception $e ) {
				$res = array( 'asn' => null );
			}
		}

		return LocationResult::fromArray( $res );
	}

	public function databaseReady( array $settings ): bool {
		$path = isset( $settings['GeoLite2']['ip_path'] ) ? $settings['GeoLite2']['ip_path'] : '';
		if ( empty( $path ) ) {
			$path = trailingslashit( $this->storageDir() ) . self::DB_IP;
		}
		$path = apply_filters( 'ip-location-block-geolite2-path', $path );

		return ! empty( $path ) && @file_exists( $path );
	}

	private function apiUrl( string $type, ?string $key = '' ): ?string {
		if ( 'country' === $type ) {
			return apply_filters( 'ip-location-block-geolite2-zip-ip', sprintf( self::ZIP_IP, (string) $key ) );
		}
		if ( 'asn' === $type ) {
			return apply_filters( 'ip-location-block-geolite2-zip-asn', sprintf( self::ZIP_ASN, (string) $key ) );
		}

		return null;
	}

	public function download( array $args, array $options ): DownloadReport {
		$dir = $this->dbDir();
		$db  = isset( $options[ $this->id() ] ) ? $options[ $this->id() ] : array();

		$ip_path   = '';
		$ip_path_d = ! empty( $ip_path ) ? dirname( $ip_path ) : '';
		$ip_last   = isset( $db['ip_last'] ) ? $db['ip_last'] : '';

		if ( $dir !== $ip_path_d . '/' ) {
			$ip_path = $dir . self::DB_IP;
		}

		$api_key = isset( $options['providers'][ $this->id() ] ) ? $options['providers'][ $this->id() ] : null;

		$ip_path   = apply_filters( 'ip-location-block-geolite2-path', $ip_path );
		$res       = array();
		$res['ip'] = Util::download_zip(
			$this->apiUrl( 'country', $api_key ),
			$args + array( 'method' => 'GET' ),
			array( $ip_path, 'COPYRIGHT.txt', 'LICENSE.txt' ),
			$ip_last
		);

		if ( ! empty( $res['ip']['filename'] ) ) {
			$db['ip_path'] = $res['ip']['filename'];
		}
		if ( ! empty( $res['ip']['modified'] ) ) {
			$db['ip_last'] = $res['ip']['modified'];
		}

		if ( ! empty( $options['use_asn'] ) || ! empty( $db['asn_path'] ) ) {
			if ( ! isset( $db['asn_path'] ) ) {
				$db['asn_path'] = '';
			}
			if ( $dir !== dirname( $db['asn_path'] ) . '/' ) {
				$db['asn_path'] = $dir . self::DB_ASN;
			}
			$res['asn'] = Util::download_zip(
				$this->apiUrl( 'asn', $api_key ),
				$args + array( 'method' => 'GET' ),
				array( $db['asn_path'], 'COPYRIGHT.txt', 'LICENSE.txt' ),
				isset( $db['asn_last'] ) ? $db['asn_last'] : ''
			);

			if ( ! empty( $res['asn']['filename'] ) ) {
				$db['asn_path'] = $res['asn']['filename'];
			}
			if ( ! empty( $res['asn']['modified'] ) ) {
				$db['asn_last'] = $res['asn']['modified'];
			}
		}

		return new DownloadReport( $res, $db );
	}

	/**
	 * Attribution HTML required by the MaxMind GeoLite2 CC BY-SA 4.0 license.
	 */
	public function getAttribution(): string {
		return 'This product includes GeoLite2 data created by MaxMind, available from <a class="ip-location-block-link" href="https://www.maxmind.com" rel=noreferrer target=_blank>https://www.maxmind.com</a>. (<a href="https://creativecommons.org/licenses/by-sa/4.0/" title="Creative Commons &mdash; Attribution-ShareAlike 4.0 International &mdash; CC BY-SA 4.0" rel=noreferrer target=_blank>CC BY-SA 4.0</a>)';
	}

	/**
	 * Register the GeoLite2 settings field(s) (discovered via method_exists on
	 * the compat adapter).
	 *
	 * @param mixed ...$callback The classic callback pair.
	 */
	public function addSettingsField( string $field, string $section, string $option_slug, string $option_name, array $options, $callback, string $str_path, string $str_last ): void {
		$fs = FileSystem::init( __FILE__ . '(' . __FUNCTION__ . ')' );

		$db  = $options[ $field ];
		$dir = $this->dbDir();
		$msg = __( 'Database file does not exist.', 'ip-location-block' );

		$ip_path   = '';
		$ip_path_d = ! empty( $ip_path ) ? dirname( $ip_path ) : '';
		$ip_last   = isset( $db['ip_last'] ) ? $db['ip_last'] : '';

		if ( $dir !== $ip_path_d . DIRECTORY_SEPARATOR ) {
			$ip_path = $dir . self::DB_IP;
		}

		$ip_path = apply_filters( 'ip-location-block-geolite2-path', $ip_path );

		if ( $ip_path && $fs->exists( $ip_path ) ) {
			if ( empty( $ip_last ) ) {
				$ip_last = filemtime( $ip_path );
			}
			$date = sprintf( $str_last, Util::localdate( $ip_last ) );
		} else {
			if ( empty( $options['providers']['GeoLite2'] ) ) {
				$date = __( 'GeoLite2 not configured. Key is missing.', 'ip-location-block' );
			} else {
				$date = $msg;
			}
		}

		add_settings_field(
			$option_name . $field . '_ip',
			"$field $str_path<br />(<a rel='noreferrer' href='" . self::DOWNLOAD_PAGE . "' title='" . self::ZIP_IP . "'>IPv4 and IPv6</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ip_path',
				'value'     => $ip_path,
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ip" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);

		if ( ! empty( $db['use_asn'] ) || ! empty( $db['asn_path'] ) ) :

			if ( $dir !== dirname( $db['asn_path'] ) . '/' ) {
				$db['asn_path'] = $dir . self::DB_ASN;
			}

			if ( $fs->exists( $db['asn_path'] ) ) {
				$date = sprintf( $str_last, Util::localdate( $db['asn_last'] ) );
			} else {
				$date = $msg;
			}

			add_settings_field(
				$option_name . $field . '_asn',
				"$field $str_path<br />(<a rel='noreferrer' href='" . self::DOWNLOAD_PAGE . "' title='" . self::ZIP_ASN . "'>ASN for IPv4 and IPv6</a>)",
				$callback,
				$option_slug,
				$section,
				array(
					'type'      => 'text',
					'option'    => $option_name,
					'field'     => $field,
					'sub-field' => 'asn_path',
					'value'     => $db['asn_path'],
					'disabled'  => true,
					'after'     => '<br /><p id="ip-location-block-' . $field . '-asn" style="margin-left: 0.2em">' . $date . '</p>',
				)
			);

		endif;
	}
}
