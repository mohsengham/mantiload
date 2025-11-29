<?php
namespace MantiLoad\Ajax;
use MantiLoad\Search\Search_Engine;
defined( 'ABSPATH' ) || exit;

class Ajax_Handler {
	private $search;
	
	public function __construct() {
		$this->search = new Search_Engine();
		// Legacy AJAX for backward compatibility
		\add_action( 'wp_ajax_mantiload_search', array( $this, 'ajax_search' ) );
		\add_action( 'wp_ajax_nopriv_mantiload_search', array( $this, 'ajax_search' ) );
		// High-performance REST API endpoint
		\add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}
	
	public function register_rest_routes() {
		\register_rest_route( 'mantiload/v1', '/search', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_search' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'query' => array(
					'required' => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'post_type' => array(
					'required' => false,
					'default' => 'product',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'limit' => array(
					'required' => false,
					'default' => 10,
					'sanitize_callback' => 'absint',
				),
			),
		) );
	}

	public function rest_search( $request ) {
		try {
			$query = $request->get_param( 'query' );
			$post_type = $request->get_param( 'post_type' );
			$limit = $request->get_param( 'limit' );


			$results = $this->search->search( $query, array(
				'post_type' => $post_type,
				'limit' => $limit,
			) );


			return new \WP_REST_Response( $results, 200 );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'search_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	public function ajax_search() {
		\check_ajax_referer( 'mantiload-search', 'nonce' );

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ?? 'product' ) );
		$limit = absint( wp_unslash( $_POST['limit'] ?? 10 ) );

		$results = $this->search->search( $query, array(
			'post_type' => $post_type,
			'limit' => $limit,
		) );

		\wp_send_json_success( $results );
	}
}
