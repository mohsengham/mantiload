<?php
/**
 * Frontend-Only Redis Cache
 *
 * CRITICAL SAFETY FEATURES:
 * - Only active on FRONTEND (!is_admin())
 * - Direct Redis connection (no wp_cache_* functions)
 * - Graceful fallback to MySQL if Redis fails
 * - Smart invalidation on product/category changes
 * - Admin area is COMPLETELY UNTOUCHED
 *
 * @package MantiLoad
 */

namespace MantiLoad\Cache;

defined( 'ABSPATH' ) || exit;

class Frontend_Cache {

	/**
	 * Redis connection
	 */
	private $redis;

	/**
	 * Cache enabled flag
	 */
	private $enabled = false;

	/**
	 * Cache key prefix (site-specific to prevent staging/production conflicts)
	 */
	private $prefix;

	/**
	 * Default TTL (5 minutes)
	 */
	private $default_ttl = 300;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Set site-specific prefix to prevent Redis key conflicts
		// Uses same hash as Manticore index for consistency
		$site_hash = substr( md5( \home_url() ), 0, 8 );
		$this->prefix = 'mantiload_fe_' . $site_hash . ':';

		// CRITICAL: Check if cache is disabled via constant
		if ( defined( 'MANTILOAD_DISABLE_CACHE' ) && MANTILOAD_DISABLE_CACHE ) {
			
			return;
		}

		// Check if Redis cache is enabled in settings
		$settings = \get_option( 'mantiload_settings', array() );
		$cache_enabled = $settings['enable_redis_cache'] ?? true; // Default: enabled

		if ( ! $cache_enabled ) {
			
			return;
		}

		// CRITICAL: Only enable on frontend
		if ( is_admin() ) {
			return;
		}

		// Connect to Redis
		$this->connect();

		// Setup invalidation hooks (these run in admin to clear frontend cache)
		$this->setup_invalidation();

		
	}

	/**
	 * Connect to Redis
	 */
	private function connect() {
		if ( ! class_exists( 'Redis' ) ) {
			
			return;
		}

		try {
			$this->redis = new \Redis();
			$connected   = $this->redis->connect( '127.0.0.1', 6379, 1 ); // 1 second timeout

			if ( ! $connected ) {
				throw new \Exception( 'Redis connection failed' );
			}

			// Set serialization mode
			$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );

			$this->enabled = true;

			
		} catch ( \Exception $e ) {
			$this->enabled = false;

			
		}
	}

	/**
	 * Get value from cache
	 *
	 * @param string $key Cache key
	 * @return mixed|false Cached value or false on miss/error
	 */
	public function get( $key ) {
		// CRITICAL: Never use cache in admin
		if ( is_admin() ) {
			return false;
		}

		// Skip if cache disabled
		if ( ! $this->enabled ) {
			return false;
		}

		// TEMPORARY: Cache enabled for ALL users (for testing/metrics)
		// Skip for logged-in users (personalized content)
		// if ( is_user_logged_in() ) {
		// 	return false;
		// }

		// Skip for AJAX requests
		if ( wp_doing_ajax() ) {
			return false;
		}

		try {
			$value = $this->redis->get( $this->prefix . $key );

			if ( $value !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			return $value;
		} catch ( \Exception $e ) {
			
			return false;
		}
	}

	/**
	 * Set value in cache
	 *
	 * @param string $key   Cache key
	 * @param mixed  $value Value to cache
	 * @param int    $ttl   Time to live in seconds (default: 300)
	 * @return bool Success
	 */
	public function set( $key, $value, $ttl = null ) {
		// CRITICAL: Never cache in admin
		if ( is_admin() ) {
			return false;
		}

		// Skip if cache disabled
		if ( ! $this->enabled ) {
			return false;
		}

		// TEMPORARY: Cache enabled for ALL users (for testing/metrics)
		// Skip for logged-in users
		// if ( is_user_logged_in() ) {
		// 	return false;
		// }

		// Skip for AJAX requests
		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( $ttl === null ) {
			$ttl = $this->default_ttl;
		}

		try {
			$result = $this->redis->setex( $this->prefix . $key, $ttl, $value );

			if ( $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			return $result;
		} catch ( \Exception $e ) {
			
			return false;
		}
	}

	/**
	 * Delete value from cache
	 *
	 * @param string $key Cache key
	 * @return bool Success
	 */
	public function delete( $key ) {
		if ( ! $this->enabled ) {
			return false;
		}

		try {
			$result = $this->redis->del( $this->prefix . $key );

			

			return $result > 0;
		} catch ( \Exception $e ) {
			
			return false;
		}
	}

	/**
	 * Delete multiple keys by pattern
	 *
	 * @param string $pattern Cache key pattern (e.g., "category_products_393_*")
	 * @return int Number of keys deleted
	 */
	public function delete_pattern( $pattern ) {
		if ( ! $this->enabled ) {
			return 0;
		}

		try {
			$keys = $this->redis->keys( $this->prefix . $pattern );

			if ( empty( $keys ) ) {
				return 0;
			}

			$deleted = $this->redis->del( $keys );

			

			return $deleted;
		} catch ( \Exception $e ) {
			
			return 0;
		}
	}

	/**
	 * Setup invalidation hooks
	 *
	 * These hooks run in ADMIN to clear FRONTEND cache
	 */
	private function setup_invalidation() {
		// Product saved/updated
		\add_action( 'save_post_product', array( $this, 'invalidate_product' ), 10, 1 );
		\add_action( 'woocommerce_update_product', array( $this, 'invalidate_product_object' ), 10, 1 );

		// Product deleted
		\add_action( 'before_delete_post', array( $this, 'invalidate_product' ), 10, 1 );

		// Product terms changed (categories/tags)
		\add_action( 'set_object_terms', array( $this, 'invalidate_product_terms' ), 10, 4 );

		// Category/tag edited
		\add_action( 'edited_product_cat', array( $this, 'invalidate_category' ), 10, 1 );
		\add_action( 'edited_product_tag', array( $this, 'invalidate_category' ), 10, 1 );

		// Meta updated (WooSort positions, visibility)
		\add_action( 'updated_post_meta', array( $this, 'check_meta_update' ), 999, 4 );
		\add_action( 'updated_postmeta', array( $this, 'check_meta_update' ), 999, 4 ); // Alternative hook name

		// Stock status changed
		\add_action( 'woocommerce_product_set_stock_status', array( $this, 'invalidate_product_object' ), 10, 1 );

		// WooSort order saved
		\add_action( 'woosort_order_saved', array( $this, 'invalidate_woosort_category' ), 10, 2 );
	}

	/**
	 * Invalidate cache for a product
	 *
	 * @param int $product_id Product ID
	 */
	public function invalidate_product( $product_id ) {
		// Only invalidate for products
		if ( get_post_type( $product_id ) !== 'product' ) {
			return;
		}

		// Get product categories
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $categories ) ) {
			return;
		}

		// Invalidate all affected categories
		foreach ( $categories as $cat_id ) {
			$this->invalidate_category( $cat_id );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$cat_count = count( $categories );
		}
	}

	/**
	 * Invalidate cache for a product (from product object)
	 *
	 * @param \WC_Product $product Product object
	 */
	public function invalidate_product_object( $product ) {
		if ( is_numeric( $product ) ) {
			$this->invalidate_product( $product );
		} elseif ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$this->invalidate_product( $product->get_id() );
		}
	}

	/**
	 * Invalidate cache when product terms change
	 *
	 * @param int    $object_id  Object ID
	 * @param array  $terms      Term IDs
	 * @param array  $tt_ids     Term taxonomy IDs
	 * @param string $taxonomy   Taxonomy name
	 */
	public function invalidate_product_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		// Only for product taxonomies
		if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
			return;
		}

		// Get OLD categories (before change)
		$old_terms = wp_get_post_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );

		// Invalidate old categories
		if ( ! is_wp_error( $old_terms ) ) {
			foreach ( $old_terms as $cat_id ) {
				$this->invalidate_category( $cat_id );
			}
		}

		// Invalidate new categories
		foreach ( $tt_ids as $cat_id ) {
			$this->invalidate_category( $cat_id );
		}
	}

	/**
	 * Invalidate cache for a category
	 *
	 * @param int $cat_id Category ID
	 */
	public function invalidate_category( $cat_id ) {
		if ( ! $this->enabled ) {
			return;
		}

		// Delete all pages for this category
		// Pattern: "category_products_{cat_id}_*"
		$pattern = "category_products_{$cat_id}_*";
		$deleted = $this->delete_pattern( $pattern );

		
	}

	/**
	 * Check if meta update should invalidate cache
	 *
	 * @param int    $meta_id    Meta ID
	 * @param int    $object_id  Object ID
	 * @param string $meta_key   Meta key
	 * @param mixed  $meta_value Meta value
	 */
	public function check_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Log ALL meta updates for debugging
		if ( strpos( $meta_key, '_mantisort_pos_' ) === 0 ) {
		}

		// Check if it's a product
		if ( get_post_type( $object_id ) !== 'product' ) {
			return;
		}

		// WooSort position changed
		if ( strpos( $meta_key, '_mantisort_pos_' ) === 0 ) {
			// Extract category ID from meta key
			$cat_id = str_replace( '_mantisort_pos_', '', $meta_key );
			$this->invalidate_category( (int) $cat_id );

			
			return;
		}

		// Visibility changed
		if ( in_array( $meta_key, array( '_visibility', 'catalog_visibility', '_stock_status' ), true ) ) {
			$this->invalidate_product( $object_id );

			
		}
	}

	/**
	 * Invalidate category cache when WooSort order is saved
	 *
	 * @param int   $category_id   Category ID
	 * @param array $product_order Product order array
	 */
	public function invalidate_woosort_category( $category_id, $product_order ) {
		if ( ! $this->enabled ) {
			return;
		}

		// Invalidate the category cache
		$this->invalidate_category( $category_id );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Statistics
	 */
	public function get_stats() {
		if ( ! $this->enabled ) {
			return array(
				'enabled' => false,
				'message' => 'Redis cache not available',
			);
		}

		try {
			$info = $this->redis->info( 'stats' );

			return array(
				'enabled'        => true,
				'keyspace_hits'  => $info['keyspace_hits'] ?? 0,
				'keyspace_misses' => $info['keyspace_misses'] ?? 0,
				'hit_rate'       => $this->calculate_hit_rate( $info ),
				'total_keys'     => $this->redis->dbSize(),
			);
		} catch ( \Exception $e ) {
			return array(
				'enabled' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Calculate cache hit rate
	 *
	 * @param array $info Redis info
	 * @return float Hit rate percentage
	 */
	private function calculate_hit_rate( $info ) {
		$hits   = $info['keyspace_hits'] ?? 0;
		$misses = $info['keyspace_misses'] ?? 0;
		$total  = $hits + $misses;

		if ( $total === 0 ) {
			return 0;
		}

		return round( ( $hits / $total ) * 100, 2 );
	}

	/**
	 * Flush all frontend cache
	 */
	public function flush_all() {
		if ( ! $this->enabled ) {
			return false;
		}

		try {
			$deleted = $this->delete_pattern( '*' );

			

			return $deleted;
		} catch ( \Exception $e ) {
			
			return false;
		}
	}
}
