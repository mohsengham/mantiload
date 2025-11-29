<?php
/**
 * Navigation Menu Cache
 *
 * Caches WordPress navigation menus to eliminate the 212+ nav_menu_item meta queries.
 *
 * SAFETY FEATURES:
 * - Only active on FRONTEND (!is_admin())
 * - Uses Frontend_Cache for Redis connection
 * - Smart invalidation when menus are updated
 * - Graceful fallback if cache fails
 *
 * @package MantiLoad
 */

namespace MantiLoad\Cache;

defined( 'ABSPATH' ) || exit;

class Menu_Cache {

	/**
	 * Frontend cache instance
	 */
	private $cache;

	/**
	 * Cache TTL (1 hour - menus rarely change)
	 */
	private $ttl = 3600;

	/**
	 * Constructor
	 *
	 * @param Frontend_Cache $cache Frontend cache instance
	 */
	public function __construct( $cache ) {
		$this->cache = $cache;

		// CRITICAL: Only enable on frontend
		if ( is_admin() ) {
			// But we DO need invalidation hooks in admin
			$this->setup_invalidation();
			return;
		}

		// Hook into menu rendering
		\add_filter( 'pre_wp_nav_menu', array( $this, 'get_cached_menu' ), 10, 2 );
		\add_filter( 'wp_nav_menu', array( $this, 'cache_menu' ), 999, 2 );

		// Setup invalidation hooks
		$this->setup_invalidation();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
	}

	/**
	 * Try to get cached menu
	 *
	 * @param string|null $output Nav menu HTML output (null by default)
	 * @param object      $args   Nav menu arguments
	 * @return string|null Cached menu HTML or null to continue
	 */
	public function get_cached_menu( $output, $args ) {
		// Skip if cache disabled
		if ( ! $this->should_cache() ) {
			return $output;
		}

		$cache_key = $this->build_cache_key( $args );
		$cached    = $this->cache->get( $cache_key );

		if ( $cached !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// Add cache marker for debugging
			return "<!-- MantiLoad Menu Cache HIT: {$cache_key} -->\n" . $cached;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		// Return null to let WordPress generate menu
		return null;
	}

	/**
	 * Cache the generated menu
	 *
	 * @param string $nav_menu Nav menu HTML output
	 * @param object $args     Nav menu arguments
	 * @return string Nav menu HTML (unchanged)
	 */
	public function cache_menu( $nav_menu, $args ) {
		// Skip if cache disabled
		if ( ! $this->should_cache() ) {
			return $nav_menu;
		}

		// Skip if menu is empty
		if ( empty( $nav_menu ) ) {
			return $nav_menu;
		}

		$cache_key = $this->build_cache_key( $args );
		$this->cache->set( $cache_key, $nav_menu, $this->ttl );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}

		return $nav_menu;
	}

	/**
	 * Build cache key for menu
	 *
	 * @param object $args Nav menu arguments
	 * @return string Cache key
	 */
	private function build_cache_key( $args ) {
		// Key components
		$components = array(
			'menu'     => $args->menu ?? 'default',
			'theme'    => $args->theme_location ?? 'none',
			'depth'    => $args->depth ?? 0,
			'container' => $args->container ?? 'div',
		);

		// Add user login status if menu differs for logged-in users
		if ( is_user_logged_in() ) {
			$components['logged_in'] = 1;
		}

		// Create cache key
		$key_string = http_build_query( $components );
		$key_hash   = md5( $key_string );

		return "menu_{$key_hash}";
	}

	/**
	 * Check if we should cache this menu
	 *
	 * @return bool
	 */
	private function should_cache() {
		// Never cache in admin
		if ( is_admin() ) {
			return false;
		}

		// Skip for AJAX
		if ( wp_doing_ajax() ) {
			return false;
		}

		// Skip for customizer preview
		if ( is_customize_preview() ) {
			return false;
		}

		return true;
	}

	/**
	 * Setup invalidation hooks
	 */
	private function setup_invalidation() {
		// Menu updated
		\add_action( 'wp_update_nav_menu', array( $this, 'invalidate_menu' ), 10, 1 );

		// Menu item updated
		\add_action( 'wp_update_nav_menu_item', array( $this, 'invalidate_menu_from_item' ), 10, 2 );

		// Menu deleted
		\add_action( 'wp_delete_nav_menu', array( $this, 'invalidate_menu' ), 10, 1 );

		// Theme location changed
		\add_action( 'update_option_theme_mods_' . \get_option( 'stylesheet' ), array( $this, 'invalidate_all_menus' ), 10 );

		// Nav menu item saved/deleted
		\add_action( 'save_post_nav_menu_item', array( $this, 'invalidate_all_menus' ), 10 );
		\add_action( 'delete_post', array( $this, 'check_nav_menu_item_delete' ), 10, 1 );
	}

	/**
	 * Invalidate all menus (when structure changes)
	 */
	public function invalidate_all_menus() {
		$deleted = $this->cache->delete_pattern( 'menu_*' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
	}

	/**
	 * Invalidate a specific menu
	 *
	 * @param int $menu_id Menu ID
	 */
	public function invalidate_menu( $menu_id ) {
		// Invalidate all menus (simpler and safer)
		$this->invalidate_all_menus();
	}

	/**
	 * Invalidate menu from menu item ID
	 *
	 * @param int $menu_id      Menu ID
	 * @param int $menu_item_id Menu item ID
	 */
	public function invalidate_menu_from_item( $menu_id, $menu_item_id ) {
		$this->invalidate_all_menus();
	}

	/**
	 * Check if deleted post is a nav menu item
	 *
	 * @param int $post_id Post ID
	 */
	public function check_nav_menu_item_delete( $post_id ) {
		if ( get_post_type( $post_id ) === 'nav_menu_item' ) {
			$this->invalidate_all_menus();
		}
	}
}
