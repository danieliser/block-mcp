<?php
/**
 * Abilities API registration and tool execution tests.
 *
 * @package GravityKit\BlockMCP\Tests
 */

declare( strict_types=1 );

use GravityKit\BlockMCP\Abilities_Registry;
use GravityKit\BlockMCP\Tool_Executor;
use GravityKit\BlockMCP\Yoast_Bridge;

/**
 * Covers the exported tool manifest and in-process tool execution.
 */
class AbilitiesRegistryTest extends RestControllerTestCase {

	/**
	 * The exported manifest must stay aligned with the npm MCP server's tool list.
	 */
	public function test_manifest_lists_all_block_mcp_tools() {
		$registry = new Abilities_Registry(
			new Tool_Executor( $this->controller, new Yoast_Bridge() ),
			$this->controller
		);

		$reflection = new \ReflectionClass( $registry );
		$method     = $reflection->getMethod( 'load_manifest' );
		$method->setAccessible( true );
		$manifest = $method->invoke( $registry );

		$this->assertIsArray( $manifest );
		$this->assertCount( 26, $manifest['tools'] );
		$names = wp_list_pluck( $manifest['tools'], 'name' );
		$this->assertContains( 'get_page_blocks', $names );
		$this->assertContains( 'edit_block_tree', $names );
	}

	/**
	 * resolve_url ability execution must reach the REST resolve handler.
	 */
	public function test_tool_executor_resolve_url_returns_post_id() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Abilities Resolve Target',
				'post_status' => 'publish',
			)
		);

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$executor = new Tool_Executor( $this->controller, new Yoast_Bridge() );
		$result   = $executor->execute(
			'resolve_url',
			array( 'url' => get_permalink( $post_id ) )
		);

		$this->assertIsArray( $result );
		$this->assertSame( $post_id, (int) $result['post_id'] );
	}

	/**
	 * Ability ids use the gk-block-mcp namespace and dashed slugs.
	 */
	public function test_ability_ids_use_expected_namespace() {
		$registry = new Abilities_Registry(
			new Tool_Executor( $this->controller, new Yoast_Bridge() ),
			$this->controller
		);

		$ids = $registry->get_ability_ids();
		$this->assertCount( 26, $ids );
		$this->assertContains( 'gk-block-mcp/get-page-blocks', $ids );
		$this->assertContains( 'gk-block-mcp/edit-block-tree', $ids );
	}
}
