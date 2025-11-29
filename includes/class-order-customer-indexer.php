<?php
/**
 * Order and Customer Bulk Indexer
 * Fast indexing for WooCommerce orders and WordPress users
 */

namespace MantiLoad\Indexer;

defined( 'ABSPATH' ) || exit;

class Order_Customer_Indexer {

	/**
	 * Index orders in bulk
	 */
	public static function index_orders( $offset, $limit ) {
		global $wpdb;

		$results = array(
			'indexed' => 0,
			'failed' => 0,
		);

		// Get orders directly from DB
		$orders = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_date, post_modified, post_status
			FROM {$wpdb->posts}
			WHERE post_type = 'shop_order'
			AND post_status != 'trash'
			ORDER BY ID DESC
			LIMIT %d OFFSET %d",
			$limit,
			$offset
		) );

		if ( empty( $orders ) ) {
			return $results;
		}

		$batch = array();
		$order_ids = array_column( $orders, 'ID' );
		$order_ids_str = implode( ',', array_map( 'intval', $order_ids ) );

		// Get ALL order meta in ONE query
		$meta_data = array();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_ids_str contains sanitized integers via implode(array_map(absint))
		$meta_results = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN ({$order_ids_str})
			AND meta_key IN (
				'_billing_first_name', '_billing_last_name', '_billing_email',
				'_billing_phone', '_billing_company',
				'_shipping_address_1', '_shipping_address_2', '_shipping_city',
				'_shipping_state', '_shipping_postcode', '_shipping_country',
				'_order_total', '_payment_method_title', '_customer_user',
				'_order_shipping'
			)"
		);

		foreach ( $meta_results as $row ) {
			$meta_data[ $row->post_id ][ $row->meta_key ] = $row->meta_value;
		}

		// Get order items in ONE query
		$order_items = array();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_ids_str contains sanitized integers via implode(array_map(absint))
		$items_results = $wpdb->get_results(
			"SELECT order_id, order_item_name
			FROM {$wpdb->prefix}woocommerce_order_items
			WHERE order_id IN ({$order_ids_str})
			AND order_item_type = 'line_item'"
		);

		foreach ( $items_results as $item ) {
			if ( ! isset( $order_items[ $item->order_id ] ) ) {
				$order_items[ $item->order_id ] = array();
			}
			$order_items[ $item->order_id ][] = $item->order_item_name;
		}

		// Get shipping methods in ONE query (concatenate multiple shipping methods if any)
		$shipping_methods = array();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_ids_str contains sanitized integers via implode(array_map(absint))
		$shipping_results = $wpdb->get_results(
			"SELECT order_id, GROUP_CONCAT(order_item_name SEPARATOR ' ') as shipping_methods
			FROM {$wpdb->prefix}woocommerce_order_items
			WHERE order_id IN ({$order_ids_str})
			AND order_item_type = 'shipping'
			GROUP BY order_id"
		);

		foreach ( $shipping_results as $item ) {
			$shipping_methods[ $item->order_id ] = $item->shipping_methods;
		}

		// Prepare batch data
		foreach ( $orders as $order ) {
			$order_id = $order->ID;
			$meta = isset( $meta_data[ $order_id ] ) ? $meta_data[ $order_id ] : array();

			// Build customer name
			$first_name = $meta['_billing_first_name'] ?? '';
			$last_name = $meta['_billing_last_name'] ?? '';
			$customer_name = trim( "$first_name $last_name" );

			// Build shipping address
			$shipping_parts = array(
				$meta['_shipping_address_1'] ?? '',
				$meta['_shipping_address_2'] ?? '',
				$meta['_shipping_city'] ?? '',
				$meta['_shipping_state'] ?? '',
				$meta['_shipping_postcode'] ?? '',
				$meta['_shipping_country'] ?? '',
			);
			$shipping_address = trim( implode( ' ', array_filter( $shipping_parts ) ) );

			// Get order items text
			$items_text = isset( $order_items[ $order_id ] ) ? implode( ' ', $order_items[ $order_id ] ) : '';

			// Get shipping method
			$shipping_method = isset( $shipping_methods[ $order_id ] ) ? $shipping_methods[ $order_id ] : '';

			$data = array(
				'title' => 'Order #' . $order_id,
				'order_number' => (string) $order_id,
				'customer_name' => \MantiLoad\Manticore_Client::normalize_numerals( $customer_name ),
				'customer_email' => $meta['_billing_email'] ?? '',
				'customer_phone' => $meta['_billing_phone'] ?? '',
				'billing_company' => \MantiLoad\Manticore_Client::normalize_numerals( $meta['_billing_company'] ?? '' ),
				'shipping_address' => \MantiLoad\Manticore_Client::normalize_numerals( $shipping_address ),
				'shipping_method' => \MantiLoad\Manticore_Client::normalize_numerals( $shipping_method ),
				'order_items' => \MantiLoad\Manticore_Client::normalize_numerals( $items_text ),
				'order_notes' => '',

				'post_type' => 'shop_order',
				'post_status' => $order->post_status,
				'post_date' => strtotime( $order->post_date ),
				'post_modified' => strtotime( $order->post_modified ),

				'order_total' => isset( $meta['_order_total'] ) ? (float) $meta['_order_total'] : 0,
				'order_status' => $order->post_status,
				'payment_method' => $meta['_payment_method_title'] ?? '',
				'customer_id' => isset( $meta['_customer_user'] ) ? (int) $meta['_customer_user'] : 0,

				// Required base fields
				'categories' => '',
				'tags' => '',
				'author' => $customer_name,
				'category_ids' => array(),
				'tag_ids' => array(),
				'post_author' => 0,
				'menu_order' => 0,
				'comment_count' => 0,
			);

			$batch[ $order_id ] = $data;
		}

		// Bulk insert to Manticore
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		if ( empty( $client ) || empty( $batch ) ) {
			$results['failed'] = count( $batch );
			return $results;
		}

		try {
			$client->bulk_insert( $index_name, $batch );
			$results['indexed'] = count( $batch );
		} catch ( \Exception $e ) {
			$results['failed'] = count( $batch );
		}

		return $results;
	}

	/**
	 * Index customers/users in bulk
	 */
	public static function index_customers( $offset, $limit ) {
		global $wpdb;

		$results = array(
			'indexed' => 0,
			'failed' => 0,
		);

		// Get users with customer or admin/shop_manager roles
		$users = get_users( array(
			'role__in' => array( 'customer', 'administrator', 'shop_manager' ),
			'number' => $limit,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'DESC',
		) );

		if ( empty( $users ) ) {
			return $results;
		}

		$batch = array();

		foreach ( $users as $user ) {
			$user_id = $user->ID;

			// Get customer data
			if ( class_exists( 'WC_Customer' ) ) {
				$customer = new \WC_Customer( $user_id );

				// Build billing address
				$billing_parts = array(
					$customer->get_billing_address_1(),
					$customer->get_billing_address_2(),
					$customer->get_billing_city(),
					$customer->get_billing_state(),
					$customer->get_billing_postcode(),
					$customer->get_billing_country(),
				);
				$billing_address = trim( implode( ' ', array_filter( $billing_parts ) ) );

				// Build shipping address
				$shipping_parts = array(
					$customer->get_shipping_address_1(),
					$customer->get_shipping_address_2(),
					$customer->get_shipping_city(),
					$customer->get_shipping_state(),
					$customer->get_shipping_postcode(),
					$customer->get_shipping_country(),
				);
				$shipping_address = trim( implode( ' ', array_filter( $shipping_parts ) ) );

				$order_count = $customer->get_order_count();
				$total_spent = $customer->get_total_spent();
				$billing_company = $customer->get_billing_company();
				$billing_phone = $customer->get_billing_phone();
			} else {
				$billing_address = '';
				$shipping_address = '';
				$order_count = 0;
				$total_spent = 0;
				$billing_company = '';
				$billing_phone = '';
			}

			// Get primary role
			$roles = $user->roles;
			$user_role = ! empty( $roles ) ? $roles[0] : 'customer';

			$data = array(
				'title' => \MantiLoad\Manticore_Client::normalize_numerals( $user->display_name ),
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
				'user_phone' => $billing_phone,
				'billing_address' => \MantiLoad\Manticore_Client::normalize_numerals( $billing_address ),

				'post_type' => 'user',
				'user_role' => $user_role,
				'post_date' => strtotime( $user->user_registered ),
				'order_count' => $order_count,
				'total_spent' => (float) $total_spent,

				// Required base fields
				'categories' => '',
				'tags' => '',
				'author' => $user->display_name,
				'post_status' => 'publish',
				'post_modified' => strtotime( $user->user_registered ),
				'category_ids' => array(),
				'tag_ids' => array(),
				'post_author' => 0,
				'menu_order' => 0,
				'comment_count' => 0,
			);

			$batch[ $user_id ] = $data;
		}

		// Bulk insert to Manticore
		$indexer = \MantiLoad\MantiLoad::instance()->indexer;
		$client = $indexer->get_client();
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', 'mantiload_fast_one' );

		if ( empty( $client ) || empty( $batch ) ) {
			$results['failed'] = count( $batch );
			return $results;
		}

		try {
			$client->bulk_insert( $index_name, $batch );
			$results['indexed'] = count( $batch );
		} catch ( \Exception $e ) {
			$results['failed'] = count( $batch );
		}

		return $results;
	}
}
