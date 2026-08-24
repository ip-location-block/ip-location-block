<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Core;

use IPLocationBlock\Core\Validator;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * Characterization tests for IP_Location_Block::check_ua() — the User-Agent
 * (bot) rule matcher behind `public.ua_list`.
 *
 * These PIN the engine's current behavior; they are a safety net for the React
 * builder / catalog / defaults work, NOT a spec to change the engine against.
 * Every expectation here was first observed against the live plugin. If the
 * engine ever changes, update the pins deliberately.
 *
 * check_ua() is a private-constructor instance method that only touches
 * $this->request_uri and the (static) check_ips helper, so it is exercised via
 * a constructor-less reflection instance with request_uri injected.
 */
final class CheckUaTest extends TestCase {

	/**
	 * The modern new-install default (mirror of MODERN_DEFAULT in
	 * admin/app/src/lib/uaPresets.js and the ua_list default in Options.php).
	 */
	private const MODERN_DEFAULT = 'Googlebot:HOST,bingbot:HOST,DuckDuckBot:HOST,Applebot:HOST,*:FEED,facebookexternalhit:*,Twitterbot:*,LinkedInBot:*,Slackbot:*,Discordbot:*,GPTBot#*,ClaudeBot#*,CCBot#*,Bytespider#*,meta-externalagent#*,AhrefsBot#*,SemrushBot#*,MJ12bot#*';

	/**
	 * Run check_ua against a fresh instance and return the 'result' it set, or
	 * null when no rule produced a verdict.
	 *
	 * @param array<string,mixed> $validate
	 */
	private function check(
		string $ua_list,
		int $dnslkup,
		string $ua,
		array $validate,
		string $request_uri = '/some-page/',
		string $referer = ''
	): ?string {
		$rc = new \ReflectionClass( Validator::class );
		$v  = $rc->newInstanceWithoutConstructor();
		$p  = $rc->getProperty( 'request_uri' );
		$p->setAccessible( true );
		$p->setValue( $v, $request_uri );

		$_SERVER['HTTP_USER_AGENT'] = $ua;
		$_SERVER['HTTP_REFERER']    = $referer;
		unset( $_GET['feed'] );

		$settings = array( 'public' => array( 'dnslkup' => $dnslkup, 'ua_list' => $ua_list ) );
		$out      = $v->check_ua( $validate, $settings );

		return $out['result'] ?? null;
	}

	/** A visitor whose reverse-DNS name was NOT resolved (host === ip). */
	private function visitor( string $code = 'US', string $asn = 'AS64500' ): array {
		return array( 'ip' => '203.0.113.10', 'host' => '203.0.113.10', 'asn' => $asn, 'code' => $code );
	}

	/** ===== BLOCK (`#`) ===== */

	public function test_block_any_blocks_matching_ua_regardless_of_dns(): void {
		$ua = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
		$this->assertSame( 'blockUA', $this->check( 'GPTBot#*', 0, $ua, $this->visitor( 'CN' ) ) );
		$this->assertSame( 'blockUA', $this->check( 'GPTBot#*', 1, $ua, $this->visitor( 'CN' ) ) );
	}

	public function test_block_any_does_not_touch_a_non_matching_ua(): void {
		$this->assertNull( $this->check( 'GPTBot#*', 0, 'Mozilla/5.0 Firefox', $this->visitor() ) );
	}

	public function test_ua_substring_matches_inside_a_product_string(): void {
		$this->assertSame(
			'blockUA',
			$this->check( 'AhrefsBot#*', 0, 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', $this->visitor() )
		);
	}

	/** ===== ALLOW (`:`) ===== */

	public function test_allow_any_passes_matching_ua(): void {
		$this->assertSame(
			'passUA',
			$this->check( 'Twitterbot:*', 0, 'Twitterbot/1.0', $this->visitor( 'CN' ) )
		);
	}

	/** ===== first match wins (precedence within the list) ===== */

	public function test_first_matching_rule_wins_allow_before_block(): void {
		$ua = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
		$this->assertSame( 'passUA', $this->check( 'GPTBot:*,GPTBot#*', 0, $ua, $this->visitor() ) );
	}

	public function test_first_matching_rule_wins_block_before_allow(): void {
		$ua = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
		$this->assertSame( 'blockUA', $this->check( 'GPTBot#*,GPTBot:*', 0, $ua, $this->visitor() ) );
	}

	/** ===== negation (`!`) ===== */

	public function test_negated_country_blocks_everywhere_except_that_country(): void {
		$this->assertNull( $this->check( 'GPTBot#!US', 0, 'GPTBot/1.0', $this->visitor( 'US' ) ) );
		$this->assertSame( 'blockUA', $this->check( 'GPTBot#!US', 0, 'GPTBot/1.0', $this->visitor( 'CN' ) ) );
	}

	/** ===== FEED ===== */

	public function test_feed_matches_only_a_feed_request(): void {
		$this->assertSame(
			'passUA',
			$this->check( '*:FEED', 0, 'AnyReader/1.0', $this->visitor(), '/feed/' )
		);
		$this->assertNull(
			$this->check( '*:FEED', 0, 'AnyReader/1.0', $this->visitor(), '/some-page/' )
		);
	}

	public function test_negated_feed_matches_a_non_feed_request(): void {
		$this->assertSame(
			'blockUA',
			$this->check( 'Badbot#!FEED', 0, 'Badbot/1.0', $this->visitor(), '/some-page/' )
		);
		$this->assertNull(
			$this->check( 'Badbot#!FEED', 0, 'Badbot/1.0', $this->visitor(), '/feed/' )
		);
	}

	/** ===== HOST (verified reverse DNS) ===== */

	public function test_host_passes_only_when_reverse_dns_resolved(): void {
		$resolved = array( 'ip' => '66.249.66.1', 'host' => 'crawl-66-249-66-1.googlebot.com', 'asn' => 'AS15169', 'code' => 'US' );
		// dnslkup on + a resolved host (host !== ip): verified pass.
		$this->assertSame( 'passUA', $this->check( 'Googlebot:HOST', 1, 'Googlebot/2.1', $resolved ) );
		// dnslkup on + host === ip (no reverse DNS name): NOT verified, no verdict.
		$this->assertNull( $this->check( 'Googlebot:HOST', 1, 'Googlebot/2.1', $this->visitor() ) );
	}

	public function test_host_is_masked_to_any_when_dns_lookup_is_off(): void {
		// With reverse DNS off, HOST is masked to `*` — the documented weakness:
		// the allow-rule passes any matching UA from any country, unverified.
		$this->assertSame( 'passUA', $this->check( 'Googlebot:HOST', 0, 'Googlebot/2.1', $this->visitor( 'CN' ) ) );
	}

	public function test_host_equals_matches_a_substring_of_the_host(): void {
		$resolved = array( 'ip' => '66.249.66.1', 'host' => 'crawl-66-249-66-1.googlebot.com', 'asn' => 'AS15169', 'code' => 'US' );
		$this->assertSame( 'passUA', $this->check( 'Googlebot:HOST=googlebot.com', 1, 'Googlebot/2.1', $resolved ) );
		$this->assertNull( $this->check( 'Googlebot:HOST=example.org', 1, 'Googlebot/2.1', $resolved ) );
	}

	/** ===== country / `*` ===== */

	public function test_country_qualifier_matches_only_that_country(): void {
		$this->assertSame( 'passUA', $this->check( 'Twitterbot:US', 0, 'Twitterbot/1.0', $this->visitor( 'US' ) ) );
		$this->assertNull( $this->check( 'Twitterbot:US', 0, 'Twitterbot/1.0', $this->visitor( 'CN' ) ) );
	}

	/** ===== IP / CIDR ===== */

	public function test_ip_cidr_qualifier_matches_addresses_in_range(): void {
		$in  = array( 'ip' => '203.0.113.10', 'host' => '203.0.113.10', 'asn' => 'AS64500', 'code' => 'US' );
		$out = array( 'ip' => '198.51.100.5', 'host' => '198.51.100.5', 'asn' => 'AS64500', 'code' => 'US' );
		$this->assertSame( 'blockUA', $this->check( 'Badbot#203.0.113.0/24', 0, 'Badbot/1.0', $in ) );
		$this->assertNull( $this->check( 'Badbot#203.0.113.0/24', 0, 'Badbot/1.0', $out ) );
	}

	/** ===== ASN ===== */

	public function test_asn_qualifier_matches_the_visitor_asn(): void {
		$this->assertSame( 'blockUA', $this->check( 'Badbot#AS15169', 0, 'Badbot/1.0', $this->visitor( 'US', 'AS15169' ) ) );
		$this->assertNull( $this->check( 'Badbot#AS15169', 0, 'Badbot/1.0', $this->visitor( 'US', 'AS64500' ) ) );
	}

	/**
	 * The `AS`-vs-American-Samoa quirk: a bare `AS` qualifier is read as an ASN
	 * check (strncmp 'AS'), NOT as the country code for American Samoa. So you
	 * cannot target American Samoa with `X:AS`; it compares against the ASN.
	 */
	public function test_bare_AS_is_treated_as_asn_not_american_samoa(): void {
		// Interpreted as ASN: matches only when the visitor's ASN is exactly 'AS'.
		$this->assertSame( 'blockUA', $this->check( 'Badbot#AS', 0, 'Badbot/1.0', $this->visitor( 'US', 'AS' ) ) );
		// A visitor actually IN American Samoa (code AS) with a normal ASN does
		// NOT match — proving `AS` is not honored as a country here.
		$this->assertNull( $this->check( 'Badbot#AS', 0, 'Badbot/1.0', $this->visitor( 'AS', 'AS64500' ) ) );
	}

	/** ===== the modern default + the compiled presets ===== */

	public function test_modern_default_blocks_ai_training_crawlers(): void {
		$this->assertSame(
			'blockUA',
			$this->check( self::MODERN_DEFAULT, 0, 'Mozilla/5.0 (compatible; GPTBot/1.2)', $this->visitor( 'CN' ) )
		);
		$this->assertSame(
			'blockUA',
			$this->check( self::MODERN_DEFAULT, 0, 'Mozilla/5.0 (compatible; ClaudeBot/1.0)', $this->visitor( 'CN' ) )
		);
	}

	public function test_modern_default_blocks_aggressive_seo_scrapers(): void {
		$this->assertSame(
			'blockUA',
			$this->check( self::MODERN_DEFAULT, 0, 'Mozilla/5.0 (compatible; AhrefsBot/7.0)', $this->visitor() )
		);
	}

	public function test_modern_default_allows_a_feed_request(): void {
		$this->assertSame(
			'passUA',
			$this->check( self::MODERN_DEFAULT, 0, 'Feedly/1.0', $this->visitor( 'CN' ), '/feed/' )
		);
	}

	public function test_modern_default_allows_social_link_preview_bots(): void {
		$this->assertSame(
			'passUA',
			$this->check( self::MODERN_DEFAULT, 0, 'facebookexternalhit/1.1', $this->visitor( 'CN' ) )
		);
	}

	public function test_modern_default_ignores_a_plain_browser(): void {
		// No UA rule decides a normal visitor — country blocking (priority 10)
		// then applies. check_ua returns no verdict.
		$this->assertNull(
			$this->check( self::MODERN_DEFAULT, 0, 'Mozilla/5.0 (Macintosh) Safari/605.1', $this->visitor() )
		);
	}

	public function test_modern_default_does_not_block_ai_search_crawlers(): void {
		// AI-search crawlers are intentionally left un-blocked for visibility.
		$this->assertNull(
			$this->check( self::MODERN_DEFAULT, 0, 'Mozilla/5.0 (compatible; PerplexityBot/1.0)', $this->visitor( 'CN' ) )
		);
	}

	/**
	 * Guard against drift between this pin, uaPresets.js and the PHP default:
	 * the Options.php `ua_list` default must contain exactly MODERN_DEFAULT.
	 */
	public function test_options_php_ships_the_modern_default(): void {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/Settings/Options.php' );
		$this->assertStringContainsString( "'ua_list'       => '" . self::MODERN_DEFAULT . "'", $source );
	}
}
