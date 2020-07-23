<?php
/**
 * Tests for Plugin_Base.
 *
 * @package Unsplash
 */

namespace Unsplash;

/**
 * Tests for Plugin_Base.
 */
class Test_Plugin_Base extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin   = get_plugin_instance();
		$this->basename = basename( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
	}

	/**
	 * Test locate_plugin.
	 *
	 * @see Plugin_Base::locate_plugin()
	 */
	public function test_locate_plugin() {
		$location = $this->plugin->locate_plugin();


		$this->assertEquals( $this->basename, $location['dir_basename'] );
		$this->assertEquals( WP_CONTENT_DIR . '/plugins/' . $this->basename, $location['dir_path'] );
		$this->assertEquals( content_url( '/plugins/' . $this->basename . '/' ), $location['dir_url'] );
	}

	/**
	 * Test relative_path.
	 *
	 * @see Plugin_Base::relative_path()
	 */
	public function test_relative_path() {
		$this->assertEquals( 'plugins/unsplash', $this->plugin->relative_path( '/var/www/html/wp-content/plugins/unsplash', 'wp-content', '/' ) );
		$this->assertEquals( 'themes/twentysixteen/plugins/unsplash', $this->plugin->relative_path( '/var/www/html/wp-content/themes/twentysixteen/plugins/unsplash', 'wp-content', '/' ) );
	}

	/**
	 * Test asset_url.
	 *
	 * @see Plugin_Base::asset_url()
	 */
	public function test_asset_url() {
		$this->assertContains( '/plugins/' . $this->basename . '/editor.js', $this->plugin->asset_url( 'editor.js' ) );
	}

	/**
	 * Tests for trigger_warning().
	 *
	 * @see Plugin_Base::trigger_warning()
	 */
	public function test_trigger_warning() {
		$obj = $this;
		// phpcs:disable
		set_error_handler(
			function( $errno, $errstr ) use ( $obj ) {
				$obj->assertEquals( 'Unsplash\Plugin: Param is 0!', $errstr );
				$obj->assertEquals( \E_USER_WARNING, $errno );
			}
		);
		// phpcs:enable
		$this->plugin->trigger_warning( 'Param is 0!', \E_USER_WARNING );
		restore_error_handler();
	}

	/**
	 * Test asset_version().
	 *
	 * @see Plugin_Base::asset_version()
	 */
	public function test_asset_version() {
		$mock = $this->getMockBuilder( 'Unsplash\Plugin' )
			->setMethods(
				[
					'is_debug',
					'is_script_debug',
				]
			)
			->getMock();

		$mock->method( 'is_debug' )
			->willReturn( false );

		$mock->method( 'is_script_debug' )
			->willReturn( false );

		$this->assertFalse( $mock->is_debug() );
		$this->assertFalse( $mock->is_script_debug() );
		$this->assertEquals( $mock->version(), $mock->asset_version() );

		$mock = $this->getMockBuilder( 'Unsplash\Plugin' )
			->setMethods(
				[
					'is_debug',
				]
			)
			->getMock();

		$mock->method( 'is_debug' )
			->willReturn( true );

		$this->assertNotEquals( $mock->version(), $mock->asset_version() );
	}

	/**
	 * Test is_wpcom_vip_prod().
	 *
	 * @see Plugin_Base::is_wpcom_vip_prod()
	 */
	public function test_is_wpcom_vip_prod() {
		if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
			$this->assertFalse( $this->plugin->is_wpcom_vip_prod() );
			define( 'WPCOM_IS_VIP_ENV', true );
		}
		$this->assertEquals( \WPCOM_IS_VIP_ENV, $this->plugin->is_wpcom_vip_prod() );
	}

	/**
	 * Test is_debug().
	 *
	 * @see Plugin_Base::is_debug()
	 */
	public function test_is_debug() {
		$this->assertEquals( \WP_DEBUG, $this->plugin->is_debug() );
	}

	/**
	 * Test is_script_debug().
	 *
	 * @see Plugin_Base::is_script_debug()
	 */
	public function test_is_script_debug() {
		$this->assertEquals( \SCRIPT_DEBUG, $this->plugin->is_script_debug() );
	}
}

// phpcs:disable
/**
 * Test_Doc_Hooks class.
 */
class Test_Doc_Hooks extends Plugin {

	/**
	 * Load this on the init action hook.
	 *
	 * @action init
	 */
	public function init_action() {}

	/**
	 * Load this on the the_content filter hook.
	 *
	 * @filter the_content
	 *
	 * @param string $content The content.
	 * @return string
	 */
	public function the_content_filter( $content ) {
		return $content;
	}
}
// phpcs:enable
