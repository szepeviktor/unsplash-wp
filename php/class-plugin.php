<?php
/**
 * Bootstraps the Unsplash plugin.
 *
 * @package Unsplash
 */

namespace Unsplash;

/**
 * Main plugin bootstrap file.
 */
class Plugin extends Plugin_Base {

	/**
	 * Hotlink class.
	 *
	 * @var Hotlink
	 */
	public $hotlink;

	/**
	 * Settings class.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * REST_Controller class.
	 *
	 * @var REST_Controller
	 */
	public $rest_controller;

	/**
	 * Post Type to add fields to.
	 */
	const POST_TYPE = 'attachment';

	/**
	 * Initiate the plugin resources.
	 *
	 * @action plugins_loaded
	 */
	public function init() {
		$this->hotlink = new Hotlink( $this );
		$this->hotlink->init();

		$this->settings = new Settings( $this );
		$this->settings->init();

		$this->rest_controller = new REST_Controller( $this );
		$this->rest_controller->init();
	}

	/**
	 * Load our media selector assets.
	 *
	 * @action wp_enqueue_media
	 */
	public function enqueue_media_scripts() {
		$asset_file = $this->dir_path . '/assets/js/media-selector.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : [];
		$version    = isset( $asset['version'] ) ? $asset['version'] : $this->asset_version();

		$dependencies   = isset( $asset['dependencies'] ) ? $asset['dependencies'] : [];
		$dependencies[] = 'media-views';
		$dependencies[] = 'wp-api-request';

		wp_enqueue_script(
			'unsplash-media-selector',
			$this->asset_url( 'assets/js/media-selector.js' ),
			$dependencies,
			$version,
			true
		);

		wp_localize_script(
			'unsplash-media-selector',
			'unsplash',
			[
				'tabTitle' => __( 'Unsplash', 'unsplash' ),
				'route'    => rest_url( 'unsplash/v1/photos' ),
				'toolbar'  => [
					'filters' => [
						'search' => [
							'label' => __( 'Search', 'unsplash' ),
						],
					],
				],
			]
		);
	}

	/**
	 * Custom wp_prepare_attachment_for_js copied from core.
	 *
	 * @param array $image Image object.
	 *
	 * @return array
	 */
	public function wp_prepare_attachment_for_js( array $image ) {
		$image = (object) $image;

		$response = [
			'id'            => $image->id,
			'title'         => '',
			'filename'      => $image->id . '.jpg',
			'url'           => $image->urls['raw'],
			'link'          => $image->links['html'],
			'alt'           => $image->alt_description,
			'author'        => $image->author,
			'description'   => $image->description,
			'caption'       => '',
			'name'          => '',
			'height'        => $image->height,
			'width'         => $image->width,
			'status'        => 'inherit',
			'uploadedTo'    => 0,
			'date'          => strtotime( $image->created_at ) * 1000,
			'modified'      => strtotime( $image->updated_at ) * 1000,
			'menuOrder'     => 0,
			'mime'          => 'image/jpeg',
			'type'          => 'image',
			'subtype'       => 'jpeg',
			'icon'          => add_query_arg(
				[
					'w'   => 150,
					'h'   => 150,
					'q'   => 85,
					'fit' => 'crop',
				],
				$image->urls['raw']
			),
			'dateFormatted' => mysql2date( __( 'F j, Y', 'unsplash' ), $image->created_at ),
			'nonces'        => [
				'update' => false,
				'delete' => false,
				'edit'   => false,
			],
			'editLink'      => false,
			'meta'          => false,
		];

		$sizes = [
			'full' => [
				'url'    => $image->urls['raw'],
				'height' => $image->height,
				'width'  => $image->width,
			],
		];

		foreach ( $this->image_sizes() as $name => $size ) {
			$url            = add_query_arg(
				[
					'w'   => $size['height'],
					'h'   => $size['width'],
					'q'   => 85,
					'fit' => 'crop',
				],
				$image->urls['raw']
			);
			$sizes[ $name ] = [
				'url'    => $url,
				'height' => $size['height'],
				'width'  => $size['width'],
			];
		}
		$response['sizes'] = $sizes;
		return $response;
	}

	/**
	 * Get a list of image sizes.
	 *
	 * @return array
	 */
	public function image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = [];

		$image_sizes = get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
		if ( 0 === count( $image_sizes ) ) {
			return $sizes;
		}

		foreach ( $image_sizes as $s ) {
			if ( in_array( $s, [ 'thumbnail', 'medium', 'medium_large', 'large' ], true ) ) {
				$sizes[ $s ]['width']  = get_option( $s . '_size_w' );
				$sizes[ $s ]['height'] = get_option( $s . '_size_h' );
			} else {
				if ( isset( $_wp_additional_image_sizes, $_wp_additional_image_sizes[ $s ] ) ) {
					$sizes[ $s ]['height'] = $_wp_additional_image_sizes[ $s ]['height'];
				}
				$sizes[ $s ]['width'] = $_wp_additional_image_sizes[ $s ]['width'];
			}
		}

		return $sizes;
	}

	/**
	 * Register meta field for attachments.
	 *
	 * @action init
	 */
	public function register_meta() {
		$default_args = [
			'single'         => true,
			'show_in_rest'   => true,
			'object_subtype' => self::POST_TYPE,
		];

		$default_object_schema = [
			'type'                 => 'object',
			'properties'           => [],
			'additionalProperties' => true,
		];

		$meta_args = [
			'original_id'       => [],
			'original_url'      => [
				'type'         => 'string',
				'show_in_rest' => [
					'name'   => 'original_url',
					'type'   => 'string',
					'schema' => [
						'type'   => 'string',
						'format' => 'uri',
					],
				],
			],
			'color'             => [],
			'unsplash_location' => [
				'type'         => 'object',
				'show_in_rest' => [
					'name'   => 'unsplash_location',
					'type'   => 'object',
					'schema' => $default_object_schema,
				],
			],
			'unsplash_sponsor'  => [
				'type'         => 'object',
				'show_in_rest' => [
					'name'   => 'unsplash_sponsor',
					'type'   => 'object',
					'schema' => $default_object_schema,
				],
			],
			'unsplash_exif'     => [
				'type'         => 'object',
				'show_in_rest' => [
					'name'   => 'unsplash_exif',
					'type'   => 'object',
					'schema' => $default_object_schema,
				],
			],
		];

		foreach ( $meta_args as $name => $args ) {
			$args = wp_parse_args( $args, $default_args );
			register_meta( 'post', $name, $args );
		}
	}

	/**
	 * Register taxonomies for attachments.
	 *
	 * @action init
	 */
	public function register_taxonomy() {
		$default_args = [
			'public'       => false,
			'rewrite'      => false,
			'hierarchical' => false,
			'show_in_rest' => true,
		];

		$tax_args = [
			'media_tag'     => [],
			'media_source'  => [
				'labels'            => [
					'name'          => esc_html__( 'Sources', 'unsplash' ),
					'singular_name' => esc_html__( 'Source', 'unsplash' ),
					'all_items'     => esc_html__( 'All Sources', 'unsplash' ),
				],
				'show_admin_column' => true,
			],
			'unsplash_user' => [
				'labels' => [
					'name'          => esc_html__( 'Users', 'unsplash' ),
					'singular_name' => esc_html__( 'User', 'unsplash' ),
					'all_items'     => esc_html__( 'All users', 'unsplash' ),
				],
			],
		];

		foreach ( $tax_args as $name => $args ) {
			$args = wp_parse_args( $args, $default_args );
			register_taxonomy( $name, self::POST_TYPE, $args );
		}
	}
}
