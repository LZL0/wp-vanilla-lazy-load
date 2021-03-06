<?php
/**
 * Plugin Name: Vanilla Lazy Load
 * Description: Lazy load images to improve page load times.
 * Version: 0.0.1
 * Text Domain: vanilla-lazy-load
 *
 * Code by the WordPress.com VIP team, TechCrunch 2011 Redesign team, and Jake Goldman (10up LLC).
 * Adapted by Wesley Luyten @luwes (https://wesleyluyten.com)
 * Uses Vanilla LazyLoad by @verlok (https://github.com/verlok/lazyload)
 *
 * License: GPL2
 */

if ( ! class_exists( 'LazyLoad_Images' ) ) :

class LazyLoad_Images {

	const version = '0.0.1';
	protected static $enabled = true;

	static function init() {
		if ( is_admin() )
			return;

		if ( ! apply_filters( 'lazyload_is_enabled', true ) ) {
			self::$enabled = false;
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_scripts' ) );
		add_action( 'wp_head', array( __CLASS__, 'setup_filters' ), 9999 ); // we don't really want to modify anything in <head> since it's mostly all metadata, e.g. OG tags
	}

	static function setup_filters() {
		add_filter( 'the_content', array( __CLASS__, 'add_image_placeholders' ), 99 ); // run this later, so other content filters have run, including image_add_wh on WP.com
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_image_placeholders' ), 11 );
		add_filter( 'get_avatar', array( __CLASS__, 'add_image_placeholders' ), 11 );
	}

	static function add_scripts() {
		wp_enqueue_script( 'vanilla-lazy-load-js',  self::get_url( 'js/vanilla-lazy-load.js' ), array( 'jquery' ), self::version, true );
		wp_enqueue_script( 'lazyload-js', self::get_url( 'js/lazyload.min.js' ), null, self::version, true );
	}

	static function add_image_placeholders( $content ) {
		if ( ! self::is_enabled() )
			return $content;

		// Don't lazyload for feeds, previews, mobile
		if( is_feed() || is_preview() )
			return $content;

		// Don't lazy-load if the content has already been run through previously
		if ( false !== strpos( $content, 'data-original=' ) )
			return $content;

		// This is a pretty simple regex, but it works
		$content = preg_replace_callback( '#<(img)([^>]+?)(>(.*?)</\\1>|[\/]?>)#si', array( __CLASS__, 'process_image' ), $content );

		return $content;
	}

	static function process_image( $matches ) {
		// In case you want to change the placeholder image
		$placeholder_image = apply_filters( 'lazyload_images_placeholder_image', self::get_url( 'images/1x1.trans.gif' ) );

		$old_attributes_str = $matches[2];
		$old_attributes = wp_kses_hair( $old_attributes_str, wp_allowed_protocols() );

		if ( empty( $old_attributes['src'] ) ) {
			return $matches[0];
		}

		$image_src = $old_attributes['src']['value'];

		// Remove src and data-original since we manually add them
		$new_attributes = $old_attributes;
		unset( $new_attributes['src'], $new_attributes['data-original'] );

		$new_attributes_str = self::build_attributes_string( $new_attributes );

		return sprintf( '<img src="%1$s" data-original="%2$s" %3$s><noscript>%4$s</noscript>', esc_url( $placeholder_image ), esc_url( $image_src ), $new_attributes_str, $matches[0] );
	}

	private static function build_attributes_string( $attributes ) {
		$string = array();
		foreach ( $attributes as $name => $attribute ) {
			$value = $attribute['value'];
			if ( '' === $value ) {
				$string[] = sprintf( '%s', $name );
			} else {
				$string[] = sprintf( '%s="%s"', $name, esc_attr( $value ) );
			}
		}
		return implode( ' ', $string );
	}

	static function is_enabled() {
		return self::$enabled;
	}

	static function get_url( $path = '' ) {
		return plugins_url( ltrim( $path, '/' ), __FILE__ );
	}
}

function lazyload_images_add_placeholders( $content ) {
	return LazyLoad_Images::add_image_placeholders( $content );
}

add_action( 'init', array( 'LazyLoad_Images', 'init' ) );

endif;
