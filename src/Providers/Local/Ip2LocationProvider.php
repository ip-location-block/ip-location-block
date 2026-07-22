<?php
/**
 * IP2Location local provider.
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
use IPLocationBlock\Vendor\IP2Location\Database;

/**
 * Ports IP_Location_Block_API_IP2Location. Lookups run against the SCOPED
 * IP2Location database library; every `ip-location-block-ip2location-*` filter
 * is preserved at its original firing point.
 */
final class Ip2LocationProvider extends AbstractLocalProvider {

	public const DB_IPV4       = 'IP2LOCATION-LITE-DB1.BIN';
	public const DB_IPV6       = 'IP2LOCATION-LITE-DB1.IPV6.BIN';
	public const ZIP_IPV4      = 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.BIN.ZIP';
	public const ZIP_IPV6      = 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP';
	public const DOWNLOAD_PAGE = 'https://lite.ip2location.com/database/ip-country';

	public function id(): string {
		return 'IP2Location';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / LGPLv3';
	}

	public function link(): ?string {
		return 'https://lite.ip2location.com/';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_NOT_REQUIRED;
	}

	public function implicitlyEnabled(): bool {
		return true;
	}

	public function limits(): ?array {
		return array( 'System memory' );
	}

	/**
	 * transform_table: LocationResult key => IP2Location record key.
	 *
	 * @return array<string,string>
	 */
	private function transformTable(): array {
		return array(
			'countryCode' => 'countryCode',
			'countryName' => 'countryName',
			'regionName'  => 'regionName',
			'cityName'    => 'cityName',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		);
	}

	private function dbDir(): string {
		return apply_filters( 'ip-location-block-ip2location-dir', $this->storageDir() );
	}

	public function lookup( string $ip, LookupContext $context ): LocationResult {
		$settings = $context->settings;

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$file = apply_filters(
				'ip-location-block-ip2location-path',
				empty( $settings['IP2Location']['ipv4_path'] )
					? $this->dbDir() . self::DB_IPV4
					: $settings['IP2Location']['ipv4_path']
			);
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$file = empty( $settings['IP2Location']['ipv6_path'] )
				? $this->dbDir() . self::DB_IPV6
				: $settings['IP2Location']['ipv6_path'];
		} else {
			return LocationResult::error( 'illegal format' );
		}

		try {
			$geo  = new Database( $file );
			$data = $geo->lookup( $ip );
			$res  = array();
			foreach ( $this->transformTable() as $key => $val ) {
				if ( isset( $data[ $val ] ) && Database::FIELD_NOT_SUPPORTED !== $data[ $val ] ) {
					$res[ $key ] = $data[ $val ];
				}
			}
			if ( isset( $res['countryCode'] ) && strlen( (string) $res['countryCode'] ) === 2 ) {
				return LocationResult::fromArray( $res );
			}
		} catch ( \Exception $e ) {
			return LocationResult::error( $e->getMessage() );
		}

		return LocationResult::error( 'Not supported' );
	}

	public function databaseReady( array $settings ): bool {
		$path = isset( $settings['IP2Location']['ipv4_path'] ) ? $settings['IP2Location']['ipv4_path'] : '';
		if ( empty( $path ) ) {
			$path = trailingslashit( $this->storageDir() ) . self::DB_IPV4;
		}
		$path = apply_filters( 'ip-location-block-ip2location-path', $path );

		return ! empty( $path ) && @file_exists( $path );
	}

	public function download( array $args, array $options ): DownloadReport {
		$dir = $this->dbDir();
		$db  = isset( $options[ $this->id() ] ) ? $options[ $this->id() ] : array();

		$ipv4_path   = isset( $db['ipv4_path'] ) ? $db['ipv4_path'] : '';
		$ipv4_path_d = ! empty( $ipv4_path ) ? dirname( $ipv4_path ) : '';
		$ipv4_last   = isset( $db['ipv4_last'] ) ? $db['ipv4_last'] : '';
		$ipv6_path   = isset( $db['ipv6_path'] ) ? $db['ipv6_path'] : '';
		$ipv6_path_d = ! empty( $ipv6_path ) ? dirname( $ipv6_path ) : '';
		$ipv6_last   = isset( $db['ipv6_last'] ) ? $db['ipv6_last'] : '';

		if ( $dir !== $ipv4_path_d . '/' ) {
			$ipv4_path = $dir . self::DB_IPV4;
		}
		$res         = array();
		$res['ipv4'] = Util::download_zip(
			apply_filters( 'ip-location-block-ip2location-zip-ipv4', self::ZIP_IPV4 ),
			$args,
			$ipv4_path,
			$ipv4_last
		);

		if ( $dir !== $ipv6_path_d . '/' ) {
			$ipv6_path = $dir . self::DB_IPV6;
		}
		$res['ipv6'] = Util::download_zip(
			apply_filters( 'ip-location-block-ip2location-zip-ipv6', self::ZIP_IPV6 ),
			$args,
			$ipv6_path,
			$ipv6_last
		);

		if ( ! empty( $res['ipv4']['filename'] ) ) {
			$db['ipv4_path'] = $res['ipv4']['filename'];
		}
		if ( ! empty( $res['ipv6']['filename'] ) ) {
			$db['ipv6_path'] = $res['ipv6']['filename'];
		}
		if ( ! empty( $res['ipv4']['modified'] ) ) {
			$db['ipv4_last'] = $res['ipv4']['modified'];
		}
		if ( ! empty( $res['ipv6']['modified'] ) ) {
			$db['ipv6_last'] = $res['ipv6']['modified'];
		}

		return new DownloadReport( $res, $db );
	}

	/**
	 * Attribution HTML required by the IP2Location LITE CC BY-SA 4.0 license.
	 */
	public function getAttribution(): string {
		return 'This site or product includes IP2Location LITE data available from <a class="ip-location-block-link" href="https://lite.ip2location.com" rel=noreferrer target=_blank>https://lite.ip2location.com</a>. (<a href="https://creativecommons.org/licenses/by-sa/4.0/" title="Creative Commons &mdash; Attribution-ShareAlike 4.0 International &mdash; CC BY-SA 4.0" rel=noreferrer target=_blank>CC BY-SA 4.0</a>)';
	}

	/**
	 * Register the two IP2Location settings fields (the frozen classic admin
	 * discovers this via method_exists on the compat adapter).
	 *
	 * @param mixed ...$callback The classic callback pair.
	 */
	public function addSettingsField( string $field, string $section, string $option_slug, string $option_name, array $options, $callback, string $str_path, string $str_last ): void {
		$fs = FileSystem::init( __FILE__ . '(' . __FUNCTION__ . ')' );

		$db          = $options[ $field ];
		$dir         = $this->dbDir();
		$msg         = __( 'Database file does not exist.', 'ip-location-block' );
		$ipv4_path   = isset( $db['ipv4_path'] ) ? esc_html( $db['ipv4_path'] ) : '';
		$ipv4_path_d = ! empty( $ipv4_path ) ? dirname( $ipv4_path ) : '';
		$ipv4_last   = isset( $db['ipv4_last'] ) ? esc_html( $db['ipv4_last'] ) : '';
		$ipv6_path   = isset( $db['ipv6_path'] ) ? esc_html( $db['ipv6_path'] ) : '';
		$ipv6_path_d = ! empty( $ipv6_path ) ? dirname( $ipv6_path ) : '';
		$ipv6_last   = isset( $db['ipv6_last'] ) ? esc_html( $db['ipv6_last'] ) : '';

		if ( $dir !== $ipv4_path_d . DIRECTORY_SEPARATOR ) {
			$ipv4_path = $dir . self::DB_IPV4;
		}

		$ipv4_path = apply_filters( 'ip-location-block-ip2location-path', $ipv4_path );

		if ( $ipv4_path && $fs->exists( $ipv4_path ) ) {
			$date = sprintf( $str_last, Util::localdate( $ipv4_last ) );
		} else {
			$date = $msg;
		}

		add_settings_field(
			$option_name . $field . '_ipv4',
			"$field $str_path<br />(<a rel='noreferrer' href='" . self::DOWNLOAD_PAGE . "' title='" . self::ZIP_IPV4 . "'>IPv4</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ipv4_path',
				'value'     => $ipv4_path,
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ipv4" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);

		if ( $dir !== $ipv6_path_d . DIRECTORY_SEPARATOR ) {
			$ipv6_path = $dir . self::DB_IPV6;
		}

		$ipv6_path = apply_filters( 'ip-location-block-ip2location-path-ipv6', $ipv6_path );

		if ( $ipv6_path && $fs->exists( $ipv6_path ) ) {
			$date = sprintf( $str_last, Util::localdate( $ipv6_last ) );
		} else {
			$date = $msg;
		}

		add_settings_field(
			$option_name . $field . '_ipv6',
			"$field $str_path<br />(<a rel='noreferrer' href='" . self::DOWNLOAD_PAGE . "' title='" . self::ZIP_IPV6 . "'>IPv6</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ipv6_path',
				'value'     => $ipv6_path,
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ipv6" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);
	}
}
