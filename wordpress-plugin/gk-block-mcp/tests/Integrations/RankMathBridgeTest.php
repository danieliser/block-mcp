<?php
/**
 * Tests for Rank_Math_Bridge — Rank Math SEO post-meta read/write.
 *
 * Drives Rank_Math_Bridge::read_fields() and ::write_fields() directly via a
 * thin Testable subclass. Rank Math is not installed in the test environment,
 * so these exercise the bridge's own logic: field mapping, direct meta
 * read/write (with the fallback sanitizer), robots array handling, empty-value
 * clearing, read-only score, and the active gate.
 *
 * @package GravityKit\BlockMCP\Tests
 */

declare(strict_types=1);

use GravityKit\BlockMCP\Rank_Math_Bridge;

/**
 * Rank_Math_Bridge subclass that exposes the protected read/write methods.
 */
class Rank_Math_Bridge_Testable extends Rank_Math_Bridge {

	/**
	 * Public passthrough to read_fields().
	 *
	 * @param int $post_id Post to read.
	 * @return array
	 */
	public function read_fields_public( $post_id ) {
		return $this->read_fields( $post_id );
	}

	/**
	 * Public passthrough to write_fields().
	 *
	 * @param int                  $post_id Post to update.
	 * @param array<string, mixed> $fields  Field name => value pairs.
	 * @return true|\WP_Error
	 */
	public function write_fields_public( $post_id, array $fields ) {
		return $this->write_fields( $post_id, $fields );
	}
}

/**
 * Rank_Math_Bridge test suite.
 */
class RankMathBridgeTest extends WP_UnitTestCase {

	/**
	 * @var Rank_Math_Bridge_Testable
	 */
	private $bridge;

	public function set_up(): void {
		parent::set_up();
		$this->bridge = new Rank_Math_Bridge_Testable();
	}

	/**
	 * Scalar fields round-trip through their rank_math_* meta keys.
	 */
	public function test_write_then_read_round_trips_scalar_fields() {
		$post_id = self::factory()->post->create();

		$this->bridge->write_fields_public(
			$post_id,
			array(
				'title'         => 'Custom SEO Title',
				'description'   => 'Custom meta description.',
				'focus_keyword' => 'block editor',
				'canonical'     => 'https://example.com/canonical/',
			)
		);

		$this->assertSame( 'Custom SEO Title', get_post_meta( $post_id, 'rank_math_title', true ) );
		$this->assertSame( 'Custom meta description.', get_post_meta( $post_id, 'rank_math_description', true ) );
		$this->assertSame( 'block editor', get_post_meta( $post_id, 'rank_math_focus_keyword', true ) );
		$this->assertSame( 'https://example.com/canonical/', get_post_meta( $post_id, 'rank_math_canonical_url', true ) );

		$read = $this->bridge->read_fields_public( $post_id );
		$this->assertSame( $post_id, $read['post_id'] );
		$this->assertSame( 'Custom SEO Title', $read['title'] );
		$this->assertSame( 'https://example.com/canonical/', $read['canonical'] );
	}

	/**
	 * og_/twitter_ fields map to their facebook_/twitter_ meta keys.
	 */
	public function test_social_fields_map_to_correct_meta_keys() {
		$post_id = self::factory()->post->create();

		$this->bridge->write_fields_public(
			$post_id,
			array(
				'og_title'            => 'OG Title',
				'og_description'      => 'OG Desc',
				'twitter_title'       => 'TW Title',
				'twitter_description' => 'TW Desc',
			)
		);

		$this->assertSame( 'OG Title', get_post_meta( $post_id, 'rank_math_facebook_title', true ) );
		$this->assertSame( 'OG Desc', get_post_meta( $post_id, 'rank_math_facebook_description', true ) );
		$this->assertSame( 'TW Title', get_post_meta( $post_id, 'rank_math_twitter_title', true ) );
		$this->assertSame( 'TW Desc', get_post_meta( $post_id, 'rank_math_twitter_description', true ) );
	}

	/**
	 * robots is stored and returned as an array of directive strings.
	 */
	public function test_robots_is_stored_and_read_as_array() {
		$post_id = self::factory()->post->create();

		$this->bridge->write_fields_public( $post_id, array( 'robots' => array( 'noindex', 'nofollow' ) ) );

		$read = $this->bridge->read_fields_public( $post_id );
		$this->assertIsArray( $read['robots'] );
		$this->assertContains( 'noindex', $read['robots'] );
		$this->assertContains( 'nofollow', $read['robots'] );
	}

	/**
	 * An empty value clears the meta rather than storing an empty string.
	 */
	public function test_empty_value_clears_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'rank_math_title', 'Existing' );

		$this->bridge->write_fields_public( $post_id, array( 'title' => '' ) );

		$this->assertEmpty( get_post_meta( $post_id, 'rank_math_title', true ) );
	}

	/**
	 * seo_score is read-only: never written, but surfaced when set out-of-band.
	 */
	public function test_seo_score_is_read_only() {
		$post_id = self::factory()->post->create();

		$this->bridge->write_fields_public( $post_id, array( 'seo_score' => 99 ) );
		$this->assertSame( '', get_post_meta( $post_id, 'rank_math_seo_score', true ) );

		update_post_meta( $post_id, 'rank_math_seo_score', '82' );
		$read = $this->bridge->read_fields_public( $post_id );
		$this->assertSame( 82, $read['seo_score'] );
	}

	/**
	 * Rank Math is not installed in the test environment.
	 */
	public function test_is_rank_math_active_false_without_plugin() {
		$this->assertFalse( Rank_Math_Bridge::is_rank_math_active() );
	}
}
