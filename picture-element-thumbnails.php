<?php
/**
 * Plugin Name: Wordpress Picture Elements
 * Version: 1.0.4
 * Description: Add functions for using the <picture> element for WP featured images
 * Author: Ethan Clevenger
 * Author URI: http://ethanclevenger91.github.io
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

class WPPictureElement {
	function __construct() {
		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
	}

	/**
	 * Get the picture element for post thumbnails
	 * @param string $default_image_size
	 * @param array $sizes
	 * @param int $post_id
	 * @return string
	 */
	public static function get_post_picture_element($default_image_size = 'thumbnail', $sizes=false, $post_id, $attr) {
		if(has_post_thumbnail($post_id)) {
			$thumbnail_id = get_post_thumbnail_id($post_id);
			return self::get_picture_element($default_image_size, $sizes, $thumbnail_id, $attr);
		}
		return '';
	}

	public static function get_picture_element($default_image_size = 'thumbnail', $sizes = false, $attachment_id, $attr) {
		if(!$sizes) {
			return wp_get_attachment_image($attachment_id, $default_image_size, '', $attr);
		} else {
			wp_enqueue_script('wpe_picturefill');
			//Add video tags for IE9? http://scottjehl.github.io/picturefill/#ie9
			$picture = '<picture';
			foreach($attr as $att => $value) {
				$picture .= ' '.$att.'="'.$value.'"';
			}
			$picture .='>'
						. '<!--[if IE 9]><video style="display: none;"><![endif]-->'
			            . self::get_picture_srcs( $attachment_id, $sizes )
			            . '<!--[if IE 9]></video><![endif]-->'
			            . '<img srcset="' . wp_get_attachment_image_src( $attachment_id, $default_image_size)[0] . '" alt="' . self::get_img_alt( $attachment_id ) . '">
			        </picture>';
			return $picture;
		}
	}

	/**
	 * Enqueue picturefill
	 * @return null
	 */
	function wp_enqueue_scripts() {
		wp_register_script('wpe_picturefill', plugins_url('assets/js/picturefill.min.js', __FILE__ ), array(), '1.0.0', true);
	}

	/**
	 * Grab the image's alt text meta
	 * @param int $attachment_id
	 * @return string
	 */
	public static function get_img_alt( $attachment_id ) {
	    $img_alt = trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
	    return $img_alt;
	}

	/**
	 * Get the actual src tag markup
	 * @param int $post_id
	 * @param array $sizes
	 * @return string
	 */
	public static function get_picture_srcs( $thumbnail_id, $sizes ) {
		global $_wp_additional_image_sizes;
		$newSizes = array();
        $intermediate_image_sizes = $sizes;

        // Create the full array with sizes and crop info
        foreach( $intermediate_image_sizes as $breakpoint => $_size ) {

        	//if it's a default image size
            if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {
            	$newSizes[ $_size ][ 'breakpoint' ] = $breakpoint;
                $newSizes[ $_size ][ 'width' ] = get_option( $_size . '_size_w' );
                $newSizes[ $_size ][ 'height' ] = get_option( $_size . '_size_h' );
                $newSizes[ $_size ][ 'crop' ] = (bool) get_option( $_size . '_crop' );

        	//otherwise...
            } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
                $newSizes[ $_size ] = array(
                	'breakpoint' => $breakpoint,
                    'width' => $_wp_additional_image_sizes[ $_size ][ 'width' ],
                    'height' => $_wp_additional_image_sizes[ $_size ][ 'height' ],
                    'crop' =>  $_wp_additional_image_sizes[ $_size ][ 'crop' ]
                );
            } elseif ( $_size == 'full' ) {
            	$newSizes[ $_size ] = array(
            		'breakpoint' => $breakpoint,
            		'width' => 'auto',
            		'height' => 'auto',
            		'crop' => false
            	);
            }
    	}

	    $arr = array();
	    foreach ( $newSizes as $size => $details ) {
	        $image_src = wp_get_attachment_image_src( $thumbnail_id, $size );
	        $arr[] = '<source srcset="'. $image_src[0] . '" media="'.$details['breakpoint'].'">';
	    }
	    return implode( array_reverse ( $arr ) );
	}
}
$WPPictureElement = new WPPictureElement();

/**
 * Global alternative to the_post_thumbnail()
 * @param string $default_image_size
 * @param array $sizes
 * @param int $post_id
 * @return null
 */
function the_post_picture($default_image_size = 'thumbnail', $sizes=false, $post_id='', $attr=[]) {
	echo get_the_post_picture($default_image_size, $sizes, $post_id, $attr);
}

/**
 * Global alternative to get_the_post_thumbnail
 * @param string $default_image_size
 * @param array $sizes
 * @param int $post_id
 * @return string
 */
function get_the_post_picture($default_image_size = 'thumbnail', $sizes=false, $post_id='', $attr=[]) {
	if($post_id == '') {
		global $post;
		$post_id = $post->ID;
	}
	return WPPictureElement::get_post_picture_element($default_image_size, $sizes, $post_id, $attr);
}

/**
 * Global alternative to wp_get_attachment_image
 * @param string $default_image_size
 * @param array $sizes
 * @param int $attachment_id
 * @return string on success, false on failure
 */
function get_the_attachment_picture($default_image_size = 'thumbnail', $sizes=false, $attachment_id='', $attr=[]) {
	return WPPictureElement::get_picture_element($default_image_size, $sizes, $attachment_id, $attr);
}
