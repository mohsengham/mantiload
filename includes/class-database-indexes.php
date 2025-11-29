<?php
/**
 * Database Indexes Manager
 *
 * Manages one-click WooCommerce database index installation.
 * Focuses on indexes NOT covered by Index WP MySQL For Speed plugin.
 *
 * @package MantiLoad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
 * Direct database queries are required for index management.
 * This is a database optimization feature - direct queries are necessary.
 */

/**
 * Database Indexes Manager Class
 */
class MantiLoad_Database_Indexes {

	/**
	 * Database instance
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Table prefix
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Index definitions for taxonomy relationships
	 *
	 * @var array
	 */
	private $taxonomy_indexes = array();

	/**
	 * Index definitions for WooCommerce HPOS
	 *
	 * @var array
	 */
	private $woocommerce_indexes = array();

	/**
	 * Index definitions for product optimization
	 *
	 * @var array
	 */
	private $product_indexes = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->db     = $wpdb;
		$this->prefix = $wpdb->prefix;

		$this->define_indexes();
	}

	/**
	 * Define all indexes that MantiLoad can create
	 */
	private function define_indexes() {
		// Taxonomy relationship indexes (NOT covered by Index WP MySQL For Speed).
		$this->taxonomy_indexes = array(
			'mantiload_term_rel_tt_oid' => array(
				'table'       => 'term_relationships',
				'columns'     => '`term_taxonomy_id`, `object_id`',
				'description' => 'Optimizes category and attribute filtering queries',
				'benefit'     => 'Faster product category filtering',
			),
			'mantiload_term_tax_hierarchy' => array(
				'table'       => 'term_taxonomy',
				'columns'     => '`taxonomy`(20), `parent`, `term_taxonomy_id`',
				'description' => 'Optimizes hierarchical category queries',
				'benefit'     => 'Faster parent/child category navigation',
			),
			'mantiload_term_tax_taxonomy' => array(
				'table'       => 'term_taxonomy',
				'columns'     => '`taxonomy`(20), `term_taxonomy_id`',
				'description' => 'Optimizes taxonomy filtering',
				'benefit'     => 'Faster product attribute queries',
			),
		);

		// WooCommerce HPOS indexes (NOT covered by Index WP MySQL For Speed).
		$this->woocommerce_indexes = array(
			'mantiload_wc_orders_status_date' => array(
				'table'       => 'wc_orders',
				'columns'     => '`status`(20), `date_created_gmt`',
				'description' => 'Optimizes order status and date queries',
				'benefit'     => 'Faster order list filtering',
			),
			'mantiload_wc_orders_customer' => array(
				'table'       => 'wc_orders',
				'columns'     => '`customer_id`, `date_created_gmt`',
				'description' => 'Optimizes customer order history',
				'benefit'     => 'Faster "My Orders" page',
			),
			'mantiload_wc_order_items_order' => array(
				'table'       => 'woocommerce_order_items',
				'columns'     => '`order_id`, `order_item_type`(20)',
				'description' => 'Optimizes order item queries',
				'benefit'     => 'Faster order details loading',
			),
		);

		// Product-specific indexes (NOT covered by Index WP MySQL For Speed).
		$this->product_indexes = array(
			'mantiload_posts_product_status' => array(
				'table'       => 'posts',
				'columns'     => '`post_type`(20), `post_status`(20), `post_date`',
				'description' => 'Optimizes product listing queries',
				'benefit'     => 'Faster product catalog loading',
			),
			'mantiload_posts_product_parent' => array(
				'table'       => 'posts',
				'columns'     => '`post_parent`, `post_type`(20), `post_status`(20)',
				'description' => 'Optimizes product variation queries',
				'benefit'     => 'Faster variable product loading',
			),
		);
	}

	/**
	 * Check if Index WP MySQL For Speed plugin is active
	 *
	 * @return bool
	 */
	public function is_index_wp_mysql_active() {
		return is_plugin_active( 'index-wp-mysql-for-speed/index-wp-mysql-for-speed.php' );
	}

	/**
	 * Get all index definitions
	 *
	 * @return array
	 */
	public function get_all_indexes() {
		return array_merge(
			$this->taxonomy_indexes,
			$this->woocommerce_indexes,
			$this->product_indexes
		);
	}

	/**
	 * Get index status for a specific index
	 *
	 * @param string $index_name Index name.
	 * @param string $table_name Table name.
	 * @return bool
	 */
	public function index_exists( $index_name, $table_name ) {
		$full_table_name = $this->prefix . $table_name;

		$result = $this->db->get_results(
			$this->db->prepare(
				'SHOW INDEX FROM `%1s` WHERE Key_name = %s',
				$full_table_name,
				$index_name
			),
			ARRAY_A
		);

		return ! empty( $result );
	}

	/**
	 * Check if a table exists
	 *
	 * @param string $table_name Table name (without prefix).
	 * @return bool
	 */
	public function table_exists( $table_name ) {
		$full_table_name = $this->prefix . $table_name;

		$result = $this->db->get_var(
			$this->db->prepare(
				'SHOW TABLES LIKE %s',
				$full_table_name
			)
		);

		return ! empty( $result );
	}

	/**
	 * Get status of all indexes
	 *
	 * @return array
	 */
	public function get_indexes_status() {
		$all_indexes = $this->get_all_indexes();
		$status      = array();

		foreach ( $all_indexes as $index_name => $index_data ) {
			$table_name = $index_data['table'];

			$status[ $index_name ] = array(
				'table'       => $table_name,
				'description' => $index_data['description'],
				'benefit'     => $index_data['benefit'],
				'table_exists' => $this->table_exists( $table_name ),
				'index_exists' => false,
				'installable'  => false,
			);

			if ( $status[ $index_name ]['table_exists'] ) {
				$status[ $index_name ]['index_exists'] = $this->index_exists( $index_name, $table_name );
				$status[ $index_name ]['installable']  = ! $status[ $index_name ]['index_exists'];
			}
		}

		return $status;
	}

	/**
	 * Create a single index
	 *
	 * @param string $index_name Index name.
	 * @return array Result with success status and message.
	 */
	public function create_index( $index_name ) {
		$all_indexes = $this->get_all_indexes();

		if ( ! isset( $all_indexes[ $index_name ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: index name */
					esc_html__( 'Index %s not found in definitions', 'mantiload' ),
					$index_name
				),
			);
		}

		$index_data      = $all_indexes[ $index_name ];
		$table_name      = $index_data['table'];
		$full_table_name = $this->prefix . $table_name;

		// Check if table exists.
		if ( ! $this->table_exists( $table_name ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: table name */
					esc_html__( 'Table %s does not exist', 'mantiload' ),
					$full_table_name
				),
			);
		}

		// Check if index already exists.
		if ( $this->index_exists( $index_name, $table_name ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: index name */
					esc_html__( 'Index %s already exists', 'mantiload' ),
					$index_name
				),
			);
		}

		// Create the index.
		$sql = sprintf(
			'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
			$full_table_name,
			$index_name,
			$index_data['columns']
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table and column names cannot be prepared, validated above.
		$result = $this->db->query( $sql );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Failed to create index: %s', 'mantiload' ),
					$this->db->last_error
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: index name */
				esc_html__( 'Index %s created successfully', 'mantiload' ),
				$index_name
			),
		);
	}

	/**
	 * Create all installable indexes
	 *
	 * @return array Results for each index.
	 */
	public function create_all_indexes() {
		$status  = $this->get_indexes_status();
		$results = array();

		foreach ( $status as $index_name => $index_status ) {
			if ( $index_status['installable'] ) {
				$results[ $index_name ] = $this->create_index( $index_name );
			} else {
				$results[ $index_name ] = array(
					'success' => false,
					'message' => esc_html__( 'Index already exists or table not available', 'mantiload' ),
					'skipped' => true,
				);
			}
		}

		return $results;
	}

	/**
	 * Remove a single index
	 *
	 * @param string $index_name Index name.
	 * @return array Result with success status and message.
	 */
	public function remove_index( $index_name ) {
		$all_indexes = $this->get_all_indexes();

		if ( ! isset( $all_indexes[ $index_name ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: index name */
					esc_html__( 'Index %s not found in definitions', 'mantiload' ),
					$index_name
				),
			);
		}

		$index_data      = $all_indexes[ $index_name ];
		$table_name      = $index_data['table'];
		$full_table_name = $this->prefix . $table_name;

		// Check if table exists.
		if ( ! $this->table_exists( $table_name ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: table name */
					esc_html__( 'Table %s does not exist', 'mantiload' ),
					$full_table_name
				),
			);
		}

		// Check if index exists.
		if ( ! $this->index_exists( $index_name, $table_name ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: index name */
					esc_html__( 'Index %s does not exist', 'mantiload' ),
					$index_name
				),
			);
		}

		// Remove the index.
		$sql = sprintf(
			'ALTER TABLE `%s` DROP INDEX `%s`',
			$full_table_name,
			$index_name
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table and index names cannot be prepared, validated above.
		$result = $this->db->query( $sql );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Failed to remove index: %s', 'mantiload' ),
					$this->db->last_error
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: index name */
				esc_html__( 'Index %s removed successfully', 'mantiload' ),
				$index_name
			),
		);
	}

	/**
	 * Remove all MantiLoad indexes
	 *
	 * @return array Results for each index.
	 */
	public function remove_all_indexes() {
		$status  = $this->get_indexes_status();
		$results = array();

		foreach ( $status as $index_name => $index_status ) {
			if ( $index_status['index_exists'] ) {
				$results[ $index_name ] = $this->remove_index( $index_name );
			} else {
				$results[ $index_name ] = array(
					'success' => false,
					'message' => esc_html__( 'Index does not exist', 'mantiload' ),
					'skipped' => true,
				);
			}
		}

		return $results;
	}

	/**
	 * Get summary statistics
	 *
	 * @return array
	 */
	public function get_summary() {
		$status = $this->get_indexes_status();

		$total       = count( $status );
		$installed   = 0;
		$installable = 0;
		$unavailable = 0;

		foreach ( $status as $index_status ) {
			if ( $index_status['index_exists'] ) {
				$installed++;
			} elseif ( $index_status['installable'] ) {
				$installable++;
			} else {
				$unavailable++;
			}
		}

		return array(
			'total'                     => $total,
			'installed'                 => $installed,
			'installable'               => $installable,
			'unavailable'               => $unavailable,
			'index_wp_mysql_active'     => $this->is_index_wp_mysql_active(),
			'taxonomy_indexes_count'    => count( $this->taxonomy_indexes ),
			'woocommerce_indexes_count' => count( $this->woocommerce_indexes ),
			'product_indexes_count'     => count( $this->product_indexes ),
		);
	}
}
