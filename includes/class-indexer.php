<?php
/**
 * Indexer Class
 *
 * @package MantiLoad
 */

namespace MantiLoad\Indexer;

use MantiLoad\Manticore_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Indexer class
 *
 * Handles indexing of WordPress content to Manticore Search
 */
class Indexer {

	/**
	 * Manticore client
	 *
	 * @var Manticore_Client
	 */
	private $client;

	/**
	 * Index name prefix (deprecated, kept for backward compatibility)
	 *
	 * @var string
	 */
	private $index_prefix = 'wp_';

	/**
	 * Batch size for indexing
	 *
	 * @var int
	 */
	private $batch_size = 100;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->client = new Manticore_Client();
		$this->batch_size = (int) \MantiLoad\MantiLoad::get_option( 'index_batch_size', 100 );

		$this->init_hooks();
	}

	/**
	 * Get the Manticore client
	 *
	 * @return Manticore_Client
	 */
	public function get_client() {
		return $this->client;
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Hook into post save/delete
		\add_action( 'save_post', array( $this, 'index_post' ), 10, 2 );
		\add_action( 'delete_post', array( $this, 'delete_post' ) );
		\add_action( 'trash_post', array( $this, 'delete_post' ) );

		// WooCommerce specific hooks
		\add_action( 'woocommerce_update_product', array( $this, 'index_product' ) );
		\add_action( 'woocommerce_delete_product', array( $this, 'delete_post' ) );

		// Order hooks - ensure orders stay fresh
		\add_action( 'woocommerce_new_order', array( $this, 'index_order' ), 10, 2 );
		\add_action( 'woocommerce_update_order', array( $this, 'index_order' ) );
		\add_action( 'woocommerce_order_status_changed', array( $this, 'index_order' ), 10, 4 );
		\add_action( 'woocommerce_order_item_updated', array( $this, 'index_order_item_update' ), 10, 2 );
		\add_action( 'woocommerce_order_item_added', array( $this, 'index_order_item_update' ), 10, 2 );
		\add_action( 'woocommerce_order_item_deleted', array( $this, 'index_order_item_update' ), 10, 2 );

		// Customer hooks - keep customer data fresh
		\add_action( 'profile_update', array( $this, 'index_customer' ), 10, 2 );
		\add_action( 'user_register', array( $this, 'index_customer' ) );

		// Bulk actions
		\add_action( 'mantiload_auto_index', array( $this, 'auto_index_pending' ) );

		// Term updates
		\add_action( 'edited_term', array( $this, 'reindex_term_posts' ), 10, 3 );
		\add_action( 'delete_term', array( $this, 'reindex_term_posts' ), 10, 3 );
	}

	/**
	 * Get index name for post type
	 *
	 * @param string $post_type Post type
	 * @return string
	 */
	private function get_index_name( $post_type = 'post' ) {
		// Use configurable index name from settings (auto-generates unique name per installation)
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name' );

		return $index_name;
	}

	/**
	 * Create indexes for configured post types
	 *
	 * @return array Results
	 */
	public function create_indexes() {
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array( 'post', 'page', 'product' ) );

		// Add orders and customers if enabled
		$index_orders_customers = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );
		if ( $index_orders_customers ) {
			$post_types[] = 'shop_order';
			$post_types[] = 'user';
		}

		// Build unified schema combining ALL post types (they share one index)
		$unified_schema = array();
		foreach ( $post_types as $post_type ) {
			$type_schema = $this->get_index_schema( $post_type );
			$unified_schema = array_merge( $unified_schema, $type_schema );
		}

		// Create ONE index with the unified schema
		$index_name = $this->get_index_name( 'product' ); // All types use same index
		$success = $this->client->create_index( $index_name, $unified_schema );

		$results = array();
		foreach ( $post_types as $post_type ) {
			$results[ $post_type ] = array(
				'success' => $success,
				'index' => $index_name,
				'error' => $success ? '' : $this->client->get_last_error(),
			);
		}

		return $results;
	}

	/**
	 * Get index schema for post type
	 *
	 * @param string $post_type Post type
	 * @return array
	 */
	private function get_index_schema( $post_type ) {
		// Content/Excerpt indexing - configurable via admin settings
		// For products: can be disabled for faster searches (title + SKU is often enough)
		// For posts/pages: always indexed
		$index_content = \MantiLoad\MantiLoad::get_option( 'index_product_content', false );

		$schema = array(
			// Text fields for full-text search
			'title' => array( 'type' => 'text' ),
			'categories' => array( 'type' => 'text' ),
			'tags' => array( 'type' => 'text' ),
			'author' => array( 'type' => 'text' ),

			// Attributes for filtering and sorting
			'post_type' => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'post_date' => array( 'type' => 'bigint' ),
			'post_modified' => array( 'type' => 'bigint' ),
			'post_author' => array( 'type' => 'int' ),
			'menu_order' => array( 'type' => 'int' ),
			'comment_count' => array( 'type' => 'int' ),

			// Multi-valued attributes
			'category_ids' => array( 'type' => 'multi' ),
			'tag_ids' => array( 'type' => 'multi' ),
		);

		// Add content and excerpt fields conditionally
		// For products: only if index_product_content is enabled
		// For posts/pages: always add
		if ( $post_type !== 'product' || $index_content ) {
			$schema['content'] = array( 'type' => 'text' );
			$schema['excerpt'] = array( 'type' => 'text' );
		}

		// Add WooCommerce specific fields for products
		if ( $post_type === 'product' ) {
			$schema = array_merge( $schema, array(
				'sku' => array( 'type' => 'text' ),
				'short_description' => array( 'type' => 'text' ),
				'attributes' => array( 'type' => 'text' ),
				'variations' => array( 'type' => 'text' ),

				'price' => array( 'type' => 'float' ),
				'regular_price' => array( 'type' => 'float' ),
				'sale_price' => array( 'type' => 'float' ),
				'stock_quantity' => array( 'type' => 'int' ),
				'stock_status' => array( 'type' => 'string' ),
				'visibility' => array( 'type' => 'string' ),
				'featured' => array( 'type' => 'int' ),
				'on_sale' => array( 'type' => 'int' ),
				'rating' => array( 'type' => 'float' ),
				'review_count' => array( 'type' => 'int' ),
				'total_sales' => array( 'type' => 'int' ),

				// MVAs for taxonomies
				'attribute_ids' => array( 'type' => 'multi' ),
			) );

			// Dynamically add MVA fields for each WooCommerce product attribute
			// NOTE: Only attributes with ASCII-only names are supported (Manticore limitation)
			if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
				$attribute_taxonomies = wc_get_attribute_taxonomies();
				foreach ( $attribute_taxonomies as $tax ) {
					$attribute_name = wc_attribute_taxonomy_name( $tax->attribute_name );
					// Sanitize field name (replace hyphens with underscores for Manticore)
					$field_name = str_replace( '-', '_', $attribute_name ) . '_ids';

					// Skip non-ASCII attribute names (Manticore doesn't support non-ASCII field names)
					// Persian/Arabic attributes will still be indexed in attribute_ids and attributes text field
					if ( preg_match( '/^[a-zA-Z0-9_]+$/', $field_name ) ) {
						// Add MVA field for this attribute (e.g., pa_color_ids, pa_fabric_ids, pa_back_style_ids)
						$schema[ $field_name ] = array( 'type' => 'multi' );
					}
				}
			}
		}

	// Add WooCommerce order fields
	if ( $post_type === 'shop_order' ) {
		$schema = array_merge( $schema, array(
			// Searchable text fields
			'order_number' => array( 'type' => 'text' ),
			'customer_name' => array( 'type' => 'text' ),
			'customer_email' => array( 'type' => 'text' ),
			'customer_phone' => array( 'type' => 'text' ),
			'billing_company' => array( 'type' => 'text' ),
			'shipping_address' => array( 'type' => 'text' ),
			'shipping_method' => array( 'type' => 'text' ),
			'order_items' => array( 'type' => 'text' ),
			'order_notes' => array( 'type' => 'text' ),

			// Numeric/filterable fields
			'order_total' => array( 'type' => 'float' ),
			'order_status' => array( 'type' => 'string' ),
			'payment_method' => array( 'type' => 'string' ),
			'customer_id' => array( 'type' => 'int' ),
		) );
	}

	// Add user/customer fields
	if ( $post_type === 'user' ) {
		$schema = array_merge( $schema, array(
			// Text fields for full-text search
			'user_login' => array( 'type' => 'text' ),
			'user_email' => array( 'type' => 'text' ),
			'user_phone' => array( 'type' => 'text' ),
			'billing_address' => array( 'type' => 'text' ),

			// Attributes
			'user_role' => array( 'type' => 'string' ),
			'order_count' => array( 'type' => 'int' ),
			'total_spent' => array( 'type' => 'float' ),
		) );
	}

		return \apply_filters( 'mantiload_index_schema', $schema, $post_type );
	}

	/**
	 * Index a single post
	 *
	 * @param int $post_id Post ID
	 * @param \WP_Post $post Post object (optional)
	 * @return bool
	 */
	public function index_post( $post_id, $post = null ) {
		if ( ! $post ) {
			$post = \get_post( $post_id );
		}

		if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return false;
		}

		// Check if post type should be indexed
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array() );

		// Also allow shop_order if order indexing is enabled
		$index_orders = \MantiLoad\MantiLoad::get_option( 'index_orders_customers', false );
		if ( $index_orders && $post->post_type === 'shop_order' ) {
			// Allow order indexing
		} elseif ( ! in_array( $post->post_type, $post_types, true ) ) {
			return false;
		}

		$data = $this->prepare_post_data( $post );
		$index_name = $this->get_index_name( $post->post_type );

		return $this->client->replace( $index_name, $post_id, $data );
	}

	/**
	 * Index a WooCommerce product
	 *
	 * @param int $product_id Product ID
	 * @return bool
	 */
	public function index_product( $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$post = \get_post( $product_id );
		return $this->index_post( $product_id, $post );
	}

	/**
	 * Index a WooCommerce order
	 *
	 * @param int $order_id Order ID
	 * @param \WC_Order $order Order object (optional)
	 * @return bool
	 */
	public function index_order( $order_id, $order = null ) {
		// Handle different hook signatures
		if ( is_object( $order_id ) && $order_id instanceof \WC_Order ) {
			$order = $order_id;
			$order_id = $order->get_id();
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return false;
		}

		$post = \get_post( $order_id );
		return $this->index_post( $order_id, $post );
	}

	/**
	 * Handle order item updates (shipping methods, products, etc.)
	 *
	 * @param int $item_id Order item ID
	 * @param \WC_Order_Item $item Order item object
	 * @return bool
	 */
	public function index_order_item_update( $item_id, $item ) {
		if ( ! $item || ! method_exists( $item, 'get_order_id' ) ) {
			return false;
		}

		$order_id = $item->get_order_id();
		return $this->index_order( $order_id );
	}

	/**
	 * Index a WordPress user/customer
	 *
	 * @param int $user_id User ID
	 * @param \WP_User $user User object (optional)
	 * @return bool
	 */
	public function index_customer( $user_id, $user = null ) {
		if ( ! $user ) {
			$user = get_userdata( $user_id );
		}

		if ( ! $user ) {
			return false;
		}

		// Check if user has customer or admin/shop_manager role
		$allowed_roles = array( 'customer', 'administrator', 'shop_manager' );
		$user_roles = $user->roles;
		$has_allowed_role = false;

		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, $user_roles, true ) ) {
				$has_allowed_role = true;
				break;
			}
		}

		if ( ! $has_allowed_role ) {
			return false;
		}

		// Prepare customer data (similar to bulk indexer)
		$index_name = $this->get_index_name( 'user' );

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
		$user_role = ! empty( $user_roles ) ? $user_roles[0] : 'customer';

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

		return $this->client->replace( $index_name, $user_id, $data );
	}

	/**
	 * Prepare post data for indexing
	 *
	 * @param \WP_Post $post Post object
	 * @return array
	 */
	private function prepare_post_data( $post ) {
		// Content/Excerpt indexing - respects admin setting
		// For products: configurable (can disable for faster searches)
		// For posts/pages: always indexed
		$index_content = \MantiLoad\MantiLoad::get_option( 'index_product_content', false );

		// Normalize Persian/Arabic numerals to Latin for consistent search
		$data = array(
			'title' => Manticore_Client::normalize_numerals( $post->post_title ),
			'author' => Manticore_Client::normalize_numerals( get_the_author_meta( 'display_name', $post->post_author ) ),

			'post_type' => $post->post_type,
			'post_status' => $post->post_status,
			'post_date' => strtotime( $post->post_date ),
			'post_modified' => strtotime( $post->post_modified ),
			'post_author' => (int) $post->post_author,
			'menu_order' => (int) $post->menu_order,
			'comment_count' => (int) $post->comment_count,
		);

		// Add content and excerpt conditionally
		if ( $post->post_type !== 'product' || $index_content ) {
			$data['content'] = Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_content ) );
			$data['excerpt'] = $post->post_excerpt ? Manticore_Client::normalize_numerals( wp_strip_all_tags( $post->post_excerpt ) ) : '';
		}

		// Get categories (use product_cat for WooCommerce products, category for regular posts)
		$taxonomy = ( $post->post_type === 'product' ) ? 'product_cat' : 'category';
		$categories = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
		$category_names = array();
		$category_ids = array();

		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			foreach ( $categories as $cat ) {
				// Normalize category names too
				$category_names[] = Manticore_Client::normalize_numerals( $cat->name );
				$category_ids[] = $cat->term_id;
			}
		}

		$data['categories'] = implode( ' ', $category_names );
		$data['category_ids'] = $category_ids;

		// Get tags (use product_tag for WooCommerce products, post_tag for regular posts)
		$tag_taxonomy = ( $post->post_type === 'product' ) ? 'product_tag' : 'post_tag';
		$tags = wp_get_post_terms( $post->ID, $tag_taxonomy, array( 'fields' => 'all' ) );
		$tag_names = array();
		$tag_ids = array();

		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				// Normalize tag names too
				$tag_names[] = Manticore_Client::normalize_numerals( $tag->name );
				$tag_ids[] = $tag->term_id;
			}
		}

		$data['tags'] = implode( ' ', $tag_names );
		$data['tag_ids'] = $tag_ids;

		// WooCommerce product data
		if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );

			if ( $product ) {
				// Normalize SKU (could contain Persian/Arabic numerals)
				$data['sku'] = Manticore_Client::normalize_numerals( $product->get_sku() ?: '' );
				$data['short_description'] = Manticore_Client::normalize_numerals( wp_strip_all_tags( $product->get_short_description() ) );
				$data['price'] = (float) $product->get_price();
				$data['regular_price'] = (float) $product->get_regular_price();
				$data['sale_price'] = (float) $product->get_sale_price();
				$data['stock_quantity'] = $product->get_stock_quantity() ?: 0;
				$data['stock_status'] = $product->get_stock_status();
				$data['visibility'] = $product->get_catalog_visibility();
				$data['featured'] = $product->is_featured() ? 1 : 0;
				$data['on_sale'] = $product->is_on_sale() ? 1 : 0;
				$data['rating'] = (float) $product->get_average_rating();
				$data['review_count'] = (int) $product->get_review_count();
				$data['total_sales'] = (int) $product->get_total_sales();

				// Get attributes
				$attributes = $product->get_attributes();
				$attribute_text = array();
				$attribute_ids = array(); // Generic array for all attribute term IDs
				$attribute_taxonomies = array(); // Grouped by taxonomy for individual fields

				foreach ( $attributes as $attribute ) {
					if ( $attribute->is_taxonomy() ) {
						$taxonomy_name = $attribute->get_name(); // e.g., 'pa_color', 'pa_fabric'
						$terms = wp_get_post_terms( $post->ID, $taxonomy_name );

						foreach ( $terms as $term ) {
							// Normalize attribute terms
							$attribute_text[] = Manticore_Client::normalize_numerals( $term->name );
							$attribute_ids[] = $term->term_id; // Add to generic array

							// Add to taxonomy-specific array
							if ( ! isset( $attribute_taxonomies[ $taxonomy_name ] ) ) {
								$attribute_taxonomies[ $taxonomy_name ] = array();
							}
							$attribute_taxonomies[ $taxonomy_name ][] = $term->term_id;
						}
					} else {
						// Normalize custom attribute options
						$options = $attribute->get_options();
						foreach ( $options as $option ) {
							$attribute_text[] = Manticore_Client::normalize_numerals( $option );
						}
					}
				}

				$data['attributes'] = implode( ' ', $attribute_text );
				$data['attribute_ids'] = $attribute_ids; // Keep generic array for backward compatibility

				// Add individual attribute taxonomy fields (pa_color_ids, pa_fabric_ids, pa_back_style_ids, etc.)
				// NOTE: Only attributes with ASCII-only names are supported (Manticore limitation)
				foreach ( $attribute_taxonomies as $taxonomy_name => $term_ids ) {
					// Sanitize field name (replace hyphens with underscores for Manticore)
					$field_name = str_replace( '-', '_', $taxonomy_name ) . '_ids';

					// Skip non-ASCII attribute names (must match schema creation logic)
					if ( preg_match( '/^[a-zA-Z0-9_]+$/', $field_name ) ) {
						$data[ $field_name ] = $term_ids;
					}
				}

				// Get variations
				if ( $product->is_type( 'variable' ) ) {
					$variations = $product->get_available_variations();
					$variation_text = array();

					foreach ( $variations as $variation ) {
						foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
							// Normalize variation attribute values
							$variation_text[] = Manticore_Client::normalize_numerals( $attr_value );
						}
					}

					$data['variations'] = implode( ' ', $variation_text );
				} else {
					$data['variations'] = '';
				}
			}
		}

		// WooCommerce order data
		if ( $post->post_type === 'shop_order' && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $post->ID );

			if ( $order ) {
				// Get order meta data
				$order_number = $post->ID;
				$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				$customer_email = $order->get_billing_email();
				$customer_phone = $order->get_billing_phone();
				$billing_company = $order->get_billing_company();

				// Build shipping address
				$shipping_parts = array(
					$order->get_shipping_address_1(),
					$order->get_shipping_address_2(),
					$order->get_shipping_city(),
					$order->get_shipping_state(),
					$order->get_shipping_postcode(),
					$order->get_shipping_country(),
				);
				$shipping_address = trim( implode( ' ', array_filter( $shipping_parts ) ) );

				// Get shipping method
				$shipping_method = '';
				foreach ( $order->get_items( 'shipping' ) as $item ) {
					$shipping_method = $item->get_name();
					break; // Take first shipping method
				}

				// Get order items
				$order_items = array();
				foreach ( $order->get_items() as $item ) {
					$order_items[] = $item->get_name();
				}
				$order_items_text = implode( ' ', $order_items );

				// Add order-specific data
				$data['order_number'] = (string) $order_number;
				$data['customer_name'] = \MantiLoad\Manticore_Client::normalize_numerals( $customer_name );
				$data['customer_email'] = $customer_email;
				$data['customer_phone'] = \MantiLoad\Manticore_Client::normalize_numerals( $customer_phone );
				$data['billing_company'] = \MantiLoad\Manticore_Client::normalize_numerals( $billing_company );
				$data['shipping_address'] = \MantiLoad\Manticore_Client::normalize_numerals( $shipping_address );
				$data['shipping_method'] = \MantiLoad\Manticore_Client::normalize_numerals( $shipping_method );
				$data['order_items'] = \MantiLoad\Manticore_Client::normalize_numerals( $order_items_text );
				$data['order_notes'] = '';

				// Numeric/filterable fields
				$data['order_total'] = (float) $order->get_total();
				$data['order_status'] = $order->get_status();
				$data['payment_method'] = $order->get_payment_method_title();
				$data['customer_id'] = (int) $order->get_customer_id();
			}
		}

		return \apply_filters( 'mantiload_post_data', $data, $post );
	}

	/**
	 * Delete post from index
	 *
	 * @param int $post_id Post ID
	 * @return bool
	 */
	public function delete_post( $post_id ) {
		$post = \get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$index_name = $this->get_index_name( $post->post_type );
		return $this->client->delete( $index_name, $post_id );
	}

	/**
	 * Reindex all posts for given post types
	 *
	 * @param array $post_types Post types to index
	 * @param int $offset Offset for batch processing
	 * @param int $limit Limit for batch processing
	 * @return array Results
	 */
	public function reindex_all( $post_types = null, $offset = 0, $limit = 0 ) {
		if ( $post_types === null ) {
			$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array( 'post', 'page', 'product' ) );
		}

		$results = array(
			'total' => 0,
			'indexed' => 0,
			'failed' => 0,
			'time' => 0,
		);

		$start_time = microtime( true );

		foreach ( $post_types as $post_type ) {
			// If limit is 0 and offset is 0, index ALL posts (-1)
			// Otherwise, use the specified limit or batch_size
			$posts_per_page = ( $limit === 0 && $offset === 0 ) ? -1 : ( $limit > 0 ? $limit : $this->batch_size );

			$args = array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => $posts_per_page,
				'offset' => $offset,
				'orderby' => 'ID',
				'order' => 'ASC',
				'no_found_rows' => false,
			);

			$query = new \WP_Query( $args );
			$results['total'] += $query->found_posts;

			if ( $query->have_posts() ) {
				$batch_documents = array();

				while ( $query->have_posts() ) {
					$query->the_post();
					$post = \get_post();

					$data = $this->prepare_post_data( $post );
					$batch_documents[ $post->ID ] = $data;

					if ( count( $batch_documents ) >= $this->batch_size ) {
						$index_name = $this->get_index_name( $post_type );
						if ( $this->client->bulk_insert( $index_name, $batch_documents ) ) {
							$results['indexed'] += count( $batch_documents );
						} else {
							$results['failed'] += count( $batch_documents );
						}
						$batch_documents = array();
					}
				}

				// Insert remaining documents
				if ( ! empty( $batch_documents ) ) {
					$index_name = $this->get_index_name( $post_type );
					if ( $this->client->bulk_insert( $index_name, $batch_documents ) ) {
						$results['indexed'] += count( $batch_documents );
					} else {
						$results['failed'] += count( $batch_documents );
					}
				}
			}

			wp_reset_postdata();
		}

		$results['time'] = microtime( true ) - $start_time;

		return $results;
	}

	/**
	 * Auto-index pending posts
	 */
	public function auto_index_pending() {
		// Check if there are posts that need indexing
		$needs_index = \get_option( 'mantiload_needs_index', false );

		if ( $needs_index ) {
			$this->create_indexes();
			$this->reindex_all();
			\update_option( 'mantiload_needs_index', false );
		}
	}

	/**
	 * Reindex posts for a specific term
	 *
	 * @param int $term_id Term ID
	 * @param int $tt_id Term taxonomy ID
	 * @param string $taxonomy Taxonomy
	 */
	public function reindex_term_posts( $term_id, $tt_id, $taxonomy ) {
		$args = array(
			'post_type' => 'any',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $term_id,
				),
			),
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->index_post( get_the_ID() );
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Get indexing stats
	 *
	 * @return array
	 */
	public function get_stats() {
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array() );
		$stats = array();

		foreach ( $post_types as $post_type ) {
			$index_name = $this->get_index_name( $post_type );

			// Count documents filtered by post_type in the shared index
			$indexed_count = $this->count_indexed_by_post_type( $index_name, $post_type );

			$total_count = wp_count_posts( $post_type );
			$published_count = isset( $total_count->publish ) ? $total_count->publish : 0;

			$stats[ $post_type ] = array(
				'index' => $index_name,
				'indexed' => $indexed_count,
				'total' => $published_count,
				'percentage' => $published_count > 0 ? round( ( $indexed_count / $published_count ) * 100, 2 ) : 0,
			);
		}

		return $stats;
	}

	/**
	 * Count indexed documents by post type
	 *
	 * @param string $index_name Index name
	 * @param string $post_type Post type to filter
	 * @return int
	 */
	private function count_indexed_by_post_type( $index_name, $post_type ) {
		try {
			$escaped_post_type = $this->client->escape( $post_type );
			$result = $this->client->query( "SELECT COUNT(*) as total FROM {$index_name} WHERE post_type='{$escaped_post_type}'" );

			if ( $result && $row = $result->fetch_assoc() ) {
				return (int) $row['total'];
			}
		} catch ( \Exception $e ) {
			return 0;
		}

		return 0;
	}

	/**
	 * Optimize all indexes
	 *
	 * @return array
	 */
	public function optimize_indexes() {
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array() );
		$results = array();

		foreach ( $post_types as $post_type ) {
			$index_name = $this->get_index_name( $post_type );
			$results[ $post_type ] = $this->client->optimize( $index_name );
		}

		return $results;
	}

	/**
	 * Truncate all indexes
	 *
	 * @return array
	 */
	public function truncate_indexes() {
		$post_types = \MantiLoad\MantiLoad::get_option( 'post_types', array() );
		$results = array();

		foreach ( $post_types as $post_type ) {
			$index_name = $this->get_index_name( $post_type );
			$results[ $post_type ] = $this->client->truncate( $index_name );
		}

		return $results;
	}
}
