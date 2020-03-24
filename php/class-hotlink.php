<?php
/**
 * Hotlink class.
 *
 * @package Unsplash
 */

namespace Unsplash;

use WP_Query;

/**
 * WordPress hotlink interface.
 */
class Hotlink {

	/**
	 * Plugin class.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin abstraction.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Initiate the class.
	 */
	public function init() {
		$this->plugin->add_doc_hooks( $this );
	}

	/**
	 * Filter wp_get_attachment_url
	 *
	 * @param string $url Original URL.
	 * @param int    $id Attachment ID.
	 *
	 * @filter wp_get_attachment_url, 10, 2
	 *
	 * @return mixed
	 */
	public function wp_get_attachment_url( $url, $id ) {
		$original_url = $this->get_original_url( $id );
		if ( ! $original_url ) {
			return $url;
		}

		return $original_url;
	}

	/**
	 * Add unsplash image sizes to admin ajax.
	 *
	 * @param array   $response Data for admin ajax.
	 * @param WP_Post $attachment Attachment object.
	 *
	 * @filter wp_prepare_attachment_for_js, 99, 2
	 *
	 * @return mixed
	 */
	public function wp_prepare_attachment_for_js( array $response, $attachment ) {
		if ( ! is_a( $attachment, 'WP_Post' ) ) {
			return $response;
		}
		$original_url = $this->get_original_url( $attachment->ID );
		if ( ! $original_url ) {
			return $response;
		}
		$response['sizes'] = $this->plugin->add_image_sizes( $original_url, $response['width'], $response['height'] );


		return $response;
	}

	/**
	 * Add unsplash image sizes to REST API.
	 *
	 * @param WP_Response $wp_response Data for REST API.
	 * @param WP_Post     $attachment Attachment object.
	 *
	 * @filter rest_prepare_attachment, 99, 2
	 *
	 * @return mixed
	 */
	public function rest_prepare_attachment( $wp_response, $attachment ) {
		if ( ! is_a( $attachment, 'WP_Post' ) ) {
			return $wp_response;
		}
		$original_url = $this->get_original_url( $attachment->ID );
		if ( ! $original_url ) {
			return $wp_response;
		}
		$response = $wp_response->get_data();
		if ( isset( $response['media_details'] ) ) {
			$response['media_details']['sizes'] = $this->plugin->add_image_sizes( $original_url, $response['media_details']['width'], $response['media_details']['height'] );
			// Reformat image sizes as REST API response is a little differently formatted.
			$response['media_details']['sizes'] = $this->change_fields( $response['media_details']['sizes'], $response['media_details']['file'] );
			// No image sizes missing.
			if ( isset( $response['missing_image_sizes'] ) ) {
				$response['missing_image_sizes'] = [];
			}
		}

		// Return raw image url in REST API.
		if ( isset( $response['source_url'] ) ) {
			remove_filter( 'wp_get_attachment_url', [ $this, 'wp_get_attachment_url' ], 10, 2 );
			$response['source_url'] = wp_get_attachment_url( $attachment->ID );
			add_filter( 'wp_get_attachment_url', [ $this, 'wp_get_attachment_url' ], 10, 2 );
		}

		$wp_response->set_data( $response );

		return $wp_response;
	}

	/**
	 * Reformat image sizes as REST API response is a little differently formatted.
	 *
	 * @param Array  $sizes list of sizes.
	 * @param String $file File name.
	 * @return Array
	 */
	public function change_fields( array $sizes, $file ) {
		foreach ( $sizes as $size => $details ) {
			$details['file']       = $file;
			$details['source_url'] = $details['url'];
			$details['mime_type']  = 'image/jpeg';
			unset( $details['url'] );
			unset( $details['orientation'] );
			$sizes[ $size ] = $details;
		}

		return $sizes;
	}

	/**
	 * Filter image downsize.
	 *
	 * @param array        $should_resize Array.
	 * @param int          $id Attachment ID.
	 * @param array|string $size Size.
	 *
	 * @filter image_downsize, 10, 3
	 *
	 * @return mixed
	 */
	public function image_downsize( $should_resize, $id, $size ) {
		$original_url = $this->get_original_url( $id );
		if ( ! $original_url ) {
			return $should_resize;
		}
		$image_meta = wp_get_attachment_metadata( $id );
		$image_size = ( isset( $image_meta['sizes'] ) ) ? $image_meta['sizes'] : [];
		$sizes      = $this->plugin->image_sizes();
		if ( is_array( $size ) ) {
			// If array is passed, just use height and width.
			list( $width, $height ) = $size;
		} elseif ( isset( $image_size[ $size ] ) ) {
			// Get generated size from post meta.
			$height = isset( $image_size[ $size ]['height'] ) ? $image_size[ $size ]['height'] : 0;
			$width  = isset( $image_size[ $size ]['width'] ) ? $image_size[ $size ]['width'] : 0;
		} elseif ( isset( $sizes[ $size ] ) ) {
			// Get defined size.
			list( $width, $height ) = array_values( $sizes[ $size ] );
		} else {
			// If can't find image size, then use full size.
			$height = isset( $image_meta['height'] ) ? $image_meta['height'] : 0;
			$width  = isset( $image_meta['width'] ) ? $image_meta['width'] : 0;

		}

		if ( ! $width || ! $height ) {
			return $should_resize;
		}

		$original_url = $this->plugin->get_original_url_with_size( $original_url, $width, $height );

		return [ $original_url, $width, $height, false ];
	}

	/**
	 * Filters 'img' elements in post content to add hotlinked images.
	 *
	 * @see wp_image_add_srcset_and_sizes()
	 *
	 * @param string $content The raw post content to be filtered.
	 *
	 * @filter the_content, 99, 1
	 *
	 * @return string Converted content with hotlinked images.
	 */
	public function hotlink_images_in_content( $content ) {
		if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
			return $content;
		}

		$selected_images = [];
		$attachment_ids  = [];

		foreach ( $matches[0] as $image ) {
			if ( preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) ) {
				$attachment_id = absint( $class_id[1] );

				if ( $attachment_id ) {
					/*
					 * If exactly the same image tag is used more than once, overwrite it.
					 * All identical tags will be replaced later with 'str_replace()'.
					 */
					$selected_images[ $image ] = $attachment_id;
					// Overwrite the ID when the same image is included more than once.
					$attachment_ids[ $attachment_id ] = true;
				}
			}
		}

		if ( count( $attachment_ids ) > 1 ) {
			$this->prime_post_caches( array_keys( $attachment_ids ) );
		}

		foreach ( $selected_images as $image => $attachment_id ) {
			$content = str_replace( $image, $this->replace_image( $image, $attachment_id ), $content );
		}

		return $content;
	}

	/**
	 * Return inline image with hotlink images.
	 *
	 * @see wp_image_add_srcset_and_sizes()
	 *
	 * @param string $image         An HTML 'img' element to be filtered.
	 * @param int    $attachment_id Image attachment ID.
	 *
	 * @return string Converted 'img' element with 'srcset' and 'sizes' attributes added.
	 */
	public function replace_image( $image, $attachment_id ) {
		$original_url = $this->get_original_url( $attachment_id );
		if ( ! $original_url ) {
			return $image;
		}

		$image_src = preg_match( '/src="([^"]+)"/', $image, $match_src ) ? $match_src[1] : '';

		// Return early if we couldn't get the image source.
		if ( ! $image_src ) {
			return $image;
		}

		$image_meta = wp_get_attachment_metadata( $attachment_id );
		// Bail early if an image has been inserted and later edited.
		if ( $image_meta && preg_match( '/-e[0-9]{13}/', $image_meta['file'], $img_edit_hash ) && false === strpos( wp_basename( $image_src ), $img_edit_hash[0] ) ) {
			return $image;
		}

		$width  = preg_match( '/ width="([0-9]+)"/', $image, $match_width ) ? (int) $match_width[1] : 0;
		$height = preg_match( '/ height="([0-9]+)"/', $image, $match_height ) ? (int) $match_height[1] : 0;

		if ( ! $width || ! $height ) {
			/*
			 * If attempts to parse the size value failed, attempt to use the image meta data to match
			 * the image file name from 'src' against the available sizes for an attachment.
			 */
			list( $image_src_without_params ) = explode( '?', $image_src );
			$image_filename                   = wp_basename( $image_src_without_params );

			if ( wp_basename( $image_meta['file'] ) === $image_filename ) {
				$width  = (int) $image_meta['width'];
				$height = (int) $image_meta['height'];
			} else {
				foreach ( $image_meta['sizes'] as $image_size_data ) {
					if ( $image_filename === $image_size_data['file'] ) {
						$width  = (int) $image_size_data['width'];
						$height = (int) $image_size_data['height'];
						break;
					}
				}
			}
		}

		if ( ! $width || ! $height ) {
			return $image;
		}

		$new_src = $this->plugin->get_original_url_with_size( $original_url, $width, $height );
		return str_replace( $image_src, $new_src, $image );
	}


	/**
	 * Helper to get original url from post meta.
	 *
	 * @param int $id Attachment ID.
	 *
	 * @return string|bool URL or false is not found.
	 */
	protected function get_original_url( $id ) {
		return get_post_meta( $id, 'original_url', true );
	}


	/**
	 * Warm the object cache with post and meta information for all found
	 * images to avoid making individual database calls.
	 *
	 * @see https://core.trac.wordpress.org/ticket/40490
	 *
	 * @param array $attachment_ids Array of attachment ids.
	 *
	 * @return mixed
	 */
	public function prime_post_caches( array $attachment_ids ) {
		$parsed_args = [
			'post__in'               => $attachment_ids,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'post_status'            => 'any',
			'post_type'              => 'attachment',
			'suppress_filters'       => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => true,
			'nopaging'               => true, // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.nopaging_nopaging
			'orderby'                => 'post__in',
		];

		$get_attachments = new WP_Query();
		return $get_attachments->query( $parsed_args );
	}

	/**
	 * The image `src` attribute is escaped when retrieving the image tag which can mangle the `w` and `h` params we
	 * add, so the change is reverted here.
	 *
	 * @param string       $html  HTML content for the image.
	 * @param int          $id    Attachment ID.
	 * @param string       $alt   Image description for the alt attribute.
	 * @param string       $title Image description for the title attribute.
	 * @param string       $align Part of the class name for aligning the image.
	 * @param string|array $size  Size of image. Image size or array of width and height values (in that order).
	 *                            Default 'medium'.
	 *
	 * @filter get_image_tag, 10, 6
	 *
	 * @return string Image tag.
	 */
	public function get_image_tag( $html, $id, $alt, $title, $align, $size ) {
		// Verify it is an Unsplash ID.
		$original_url = $this->get_original_url( $id );
		if ( ! $original_url ) {
			return $html;
		}

		// Replace img src.
		list( $img_src ) = image_downsize( $id, $size );
		return preg_replace( '/src="([^"]+)"/', "src=\"{$img_src}\"", $html, 1 );
	}
}
