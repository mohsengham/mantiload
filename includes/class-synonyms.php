<?php
/**
 * MantiLoad Synonyms Manager
 * Smart synonym system to prevent zero results!
 *
 * @package MantiLoad
 */

namespace MantiLoad\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Synonyms class
 *
 * Manages search synonyms to improve search results
 */
class Synonyms {

	/**
	 * Table name
	 */
	private $table_name;

	/**
	 * In-memory cache for all synonyms (SPEED!)
	 * Loads once per request, eliminates 2-8 DB queries per search
	 *
	 * @var array|null
	 */
	private static $synonyms_cache = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'mantiload_synonyms';
	}

	/**
	 * Create synonyms table
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			term varchar(255) NOT NULL,
			synonyms text NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY term (term)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all synonyms
	 *
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		// Return as objects (OBJECT) instead of ARRAY_A so the view can access via ->term
		$results = $wpdb->get_results(
			"SELECT * FROM {$this->table_name} ORDER BY term ASC"
		);

		return $results ?: array();
	}

	/**
	 * Load all synonyms and build cache (ONCE per request!)
	 * Uses WordPress transient for persistence across requests
	 *
	 * @return array Synonym map [term => [synonyms]]
	 */
	private function load_all_synonyms_cached() {
		global $wpdb;

		// Try WordPress transient first (1 hour cache)
		$cached = \get_transient( 'mantiload_synonyms_cache' );
		if ( $cached !== false ) {
			return $cached;
		}

		// Load ALL synonyms from DB (once!)
		$rows = $wpdb->get_results(
			"SELECT term, synonyms FROM {$this->table_name}",
			ARRAY_A
		);

		// Build bidirectional synonym map
		$synonym_map = array();

		foreach ( $rows as $row ) {
			$term = strtolower( trim( $row['term'] ) );
			$synonyms = array_map( 'trim', explode( ',', $row['synonyms'] ) );
			$synonyms = array_filter( $synonyms );

			// Store direct mapping (term -> synonyms)
			$synonym_map[ $term ] = $synonyms;

			// Store reverse mappings (each synonym -> term + other synonyms)
			foreach ( $synonyms as $synonym ) {
				$synonym_lower = strtolower( $synonym );
				if ( ! isset( $synonym_map[ $synonym_lower ] ) ) {
					$synonym_map[ $synonym_lower ] = array();
				}
				// Add the main term and other synonyms
				$synonym_map[ $synonym_lower ][] = $term;
				foreach ( $synonyms as $other ) {
					if ( strtolower( $other ) !== $synonym_lower ) {
						$synonym_map[ $synonym_lower ][] = $other;
					}
				}
			}
		}

		// Remove duplicates and clean up
		foreach ( $synonym_map as $key => $values ) {
			$synonym_map[ $key ] = array_unique( array_filter( $values ) );
		}

		// Cache in WordPress transient (1 hour)
		\set_transient( 'mantiload_synonyms_cache', $synonym_map, HOUR_IN_SECONDS );

		return $synonym_map;
	}

	/**
	 * Get synonyms for a specific term (CACHED!)
	 * Eliminates 2 DB queries per word searched
	 *
	 * @param string $term Search term
	 * @return array
	 */
	public function get_synonyms( $term ) {
		// Load cache once per request (static variable)
		if ( self::$synonyms_cache === null ) {
			self::$synonyms_cache = $this->load_all_synonyms_cached();
		}

		$term = strtolower( trim( $term ) );

		// Lightning-fast lookup from memory!
		return self::$synonyms_cache[ $term ] ?? array();
	}

	/**
	 * Expand query with synonyms
	 *
	 * @param string $query Original query
	 * @return string Expanded query with synonyms
	 */
	public function expand_query( $query ) {
		$query = trim( $query );
		$words = explode( ' ', $query );
		$expanded_terms = array();

		foreach ( $words as $word ) {
			$word = strtolower( trim( $word ) );
			if ( empty( $word ) ) {
				continue;
			}

			// Add original word
			$terms = array( $word );

			// WILDCARD MAGIC: Add prefix matching for short queries (3-4 chars)
			// This enables "dre" to match "dress" instantly!
			$word_len = strlen( $word );
			if ( $word_len >= 3 && $word_len <= 4 ) {
				// Add wildcard version for prefix matching
				$terms[] = $word . '*';
			}

			// Get synonyms for this word
			$synonyms = $this->get_synonyms( $word );
			if ( ! empty( $synonyms ) ) {
				$terms = array_merge( $terms, $synonyms );
			}

			// Build OR clause for this word and its synonyms
			if ( count( $terms ) > 1 ) {
				$expanded_terms[] = '(' . implode( ' | ', $terms ) . ')';
			} else {
				$expanded_terms[] = $terms[0];
			}
		}

		return implode( ' ', $expanded_terms );
	}

	/**
	 * Add or update synonym
	 *
	 * @param string $term Main term
	 * @param string $synonyms Comma-separated synonyms
	 * @return bool|int
	 */
	public function save( $term, $synonyms ) {
		global $wpdb;

		$term = \sanitize_text_field( $term );
		$synonyms = sanitize_textarea_field( $synonyms );

		if ( empty( $term ) ) {
			return false;
		}

		// Check if exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE term = %s",
			$term
		) );

		$result = false;

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$this->table_name,
				array(
					'synonyms' => $synonyms,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert
			$result = $wpdb->insert(
				$this->table_name,
				array(
					'term' => $term,
					'synonyms' => $synonyms,
				),
				array( '%s', '%s' )
			);
		}

		// Clear cache after save (CRITICAL!)
		$this->clear_cache();

		return $result;
	}

	/**
	 * Delete synonym
	 *
	 * @param int $id Synonym ID
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		// Clear cache after delete (CRITICAL!)
		$this->clear_cache();

		return $result;
	}

	/**
	 * Clear synonym cache (call after save/delete)
	 * Clears both static variable and WordPress transient
	 */
	private function clear_cache() {
		// Clear static cache (current request)
		self::$synonyms_cache = null;

		// Clear WordPress transient (persistent cache)
		\delete_transient( 'mantiload_synonyms_cache' );
	}

	/**
	 * Get synonym statistics
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		return array(
			'total' => (int) $total,
		);
	}
}
