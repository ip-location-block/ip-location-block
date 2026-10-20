<?php
/**
 * Arbitrary precision math for the bundled IP2Location library.
 *
 * @package IP_Location_Block
 */

namespace IPLocationBlock\Support;

use IPLocationBlock\Vendor\bcmath_compat\BCMath as Polyfill;

/**
 * IP2Location does its IPv6 math with bc*(); the scoper rewrites those calls to
 * these methods (see scoper.inc.php). They use the native bcmath extension when
 * it's loaded and the scoped phpseclib polyfill otherwise, without ever
 * declaring global bc*() functions: other plugins detect those with
 * function_exists() and would then run on the polyfill.
 */
final class BcMath {

	public static function add( $num1, $num2, $scale = null ) {
		return self::call( 'add', array( (string) $num1, (string) $num2 ), $scale );
	}

	public static function sub( $num1, $num2, $scale = null ) {
		return self::call( 'sub', array( (string) $num1, (string) $num2 ), $scale );
	}

	public static function mul( $num1, $num2, $scale = null ) {
		return self::call( 'mul', array( (string) $num1, (string) $num2 ), $scale );
	}

	public static function div( $num1, $num2, $scale = null ) {
		return self::call( 'div', array( (string) $num1, (string) $num2 ), $scale );
	}

	public static function pow( $num, $exponent, $scale = null ) {
		return self::call( 'pow', array( (string) $num, (string) $exponent ), $scale );
	}

	public static function comp( $num1, $num2, $scale = null ) {
		return self::call( 'comp', array( (string) $num1, (string) $num2 ), $scale );
	}

	/**
	 * Like the native functions, an omitted scale means the default scale.
	 */
	private static function call( $name, array $args, $scale ) {
		if ( null !== $scale ) {
			$args[] = (int) $scale;
		}

		if ( \extension_loaded( 'bcmath' ) ) {
			return \call_user_func_array( 'bc' . $name, $args );
		}

		// BCMath's operations are private and reached through __callStatic.
		return Polyfill::__callStatic( $name, $args );
	}
}
