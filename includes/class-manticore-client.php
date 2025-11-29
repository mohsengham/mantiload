<?php
/**
 * Manticore Search Client
 *
 * @package MantiLoad
 */

namespace MantiLoad;

defined( 'ABSPATH' ) || exit;

/**
 * Manticore_Client class
 *
 * Handles all communication with Manticore Search server
 */
class Manticore_Client {

	/**
	 * MySQL connection to Manticore
	 *
	 * @var \mysqli
	 */
	private $connection = null;

	/**
	 * Last error message
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Query execution time in milliseconds
	 *
	 * @var float
	 */
	private $query_time = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->connect();
	}

	/**
	 * Get MantiCore host from settings
	 *
	 * @return string
	 */
	private function get_host() {
		return \MantiLoad\MantiLoad::get_option( 'manticore_host', MANTILOAD_HOST );
	}

	/**
	 * Get MantiCore port from settings
	 *
	 * @return int
	 */
	private function get_port() {
		return (int) \MantiLoad\MantiLoad::get_option( 'manticore_port', MANTILOAD_PORT );
	}

	/**
	 * Connect to Manticore Search
	 *
	 * @return bool
	 */
	public function connect() {
		if ( $this->connection && $this->connection->ping() ) {
			return true;
		}

		try {
			\mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Direct mysqli required for Manticore Search connection

			$this->connection = \mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Direct mysqli required for Manticore Search connection
			if ( ! $this->connection ) {
				$this->last_error = 'mysqli_init failed';
				return false;
			}

			// Disable SSL - Manticore doesn't support it
			$this->connection->options( MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false );
			$this->connection->options( MYSQLI_CLIENT_SSL, false );

			// Connect to Manticore
			if ( ! $this->connection->real_connect(
				$this->get_host(),
				'', // Manticore doesn't use username
				'', // Manticore doesn't use password
				'', // No database selection needed
				$this->get_port(),
				null,
				MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
			) ) {
				$this->last_error = $this->connection->connect_error ? $this->connection->connect_error : 'Connection failed';
				return false;
			}

			// Set character set
			$this->connection->set_charset( 'utf8mb4' );

			return true;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Execute a query
	 *
	 * @param string $query SQL query
	 * @return \mysqli_result|bool
	 */
	public function query( $query ) {
		if ( ! $this->connection ) {
			$this->connect();
		}

		if ( ! $this->connection ) {
			return false;
		}

		$start_time = microtime( true );
		$result = $this->connection->query( $query );
		$this->query_time = ( microtime( true ) - $start_time ) * 1000;

		if ( ! $result ) {
			$this->last_error = $this->connection->error;
			\do_action( 'mantiload_query_error', $query, $this->last_error );
		}

		// Log slow queries
		if ( $this->query_time > 100 ) {
			\do_action( 'mantiload_slow_query', $query, $this->query_time );
		}

		return $result;
	}

	/**
	 * Execute multiple queries
	 *
	 * @param array $queries Array of SQL queries
	 * @return array Results array
	 */
	public function multi_query( $queries ) {
		if ( ! $this->connection ) {
			$this->connect();
		}

		$results = array();
		foreach ( $queries as $query ) {
			$results[] = $this->query( $query );
		}

		return $results;
	}

	/**
	 * Escape string for query
	 *
	 * @param string $string String to escape
	 * @return string
	 */
	public function escape( $string ) {
		if ( ! $this->connection ) {
			$this->connect();
		}

		return $this->connection->real_escape_string( $string );
	}

	/**
	 * Normalize Persian/Arabic numerals to Latin numerals
	 *
	 * Converts Persian (۰-۹) and Arabic-Indic (٠-٩) numerals to Latin (0-9)
	 * so searches work consistently regardless of numeral system used.
	 *
	 * Example: "۱۲۳" → "123", "٤٥٦" → "456"
	 *
	 * @param string $text Text to normalize
	 * @return string Normalized text
	 */
	public static function normalize_numerals( $text ) {
		// Persian/Farsi numerals (U+06F0 to U+06F9)
		$persian_numerals = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		// Arabic-Indic numerals (U+0660 to U+0669)
		$arabic_numerals = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );

		// Latin numerals (0-9)
		$latin_numerals = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		// Replace Persian numerals with Latin
		$text = str_replace( $persian_numerals, $latin_numerals, $text );

		// Replace Arabic numerals with Latin
		$text = str_replace( $arabic_numerals, $latin_numerals, $text );

		return $text;
	}

	/**
	 * Create or replace an index
	 *
	 * @param string $index_name Index name
	 * @param array $schema Index schema
	 * @return bool
	 */
	public function create_index( $index_name, $schema ) {
		// Drop existing index
		$this->query( "DROP TABLE IF EXISTS {$index_name}" );

		// Build CREATE TABLE query - Manticore requires attributes first, then text fields
		$attributes = array();
		$text_fields = array();

		foreach ( $schema as $field => $config ) {
			if ( $config['type'] === 'text' ) {
				$text_fields[] = $field . ' text';
			} elseif ( $config['type'] === 'int' ) {
				$attributes[] = "{$field} int";
			} elseif ( $config['type'] === 'bigint' ) {
				$attributes[] = "{$field} bigint";
			} elseif ( $config['type'] === 'float' ) {
				$attributes[] = "{$field} float";
			} elseif ( $config['type'] === 'string' ) {
				$attributes[] = "{$field} string";
			} elseif ( $config['type'] === 'json' ) {
				$attributes[] = "{$field} json";
			} elseif ( $config['type'] === 'multi' ) {
				$attributes[] = "{$field} multi";
			} elseif ( $config['type'] === 'multi64' ) {
				$attributes[] = "{$field} multi64";
			}
		}

		// Combine all fields (text fields can come first in newer Manticore versions)
		$all_fields = array_merge( $text_fields, $attributes );

		if ( empty( $all_fields ) ) {
			return false;
		}

		// UNIVERSAL multi-language support for ALL global languages!
		// Supports: Latin, Cyrillic, Greek, Arabic, Persian, Hebrew, Chinese, Japanese, Korean, Thai, Vietnamese, and MORE!
		// NOTE: Characters in ngram_chars MUST NOT appear in charset_table (Manticore requirement)
		$charset_table = '0..9, A..Z->a..z, _, a..z, ' .
			'U+00C0..U+024F, ' .  // Latin Extended (European languages: French, German, Spanish, Polish, Czech, etc.)
			'U+0370..U+03FF, ' .  // Greek and Coptic
			'U+0400..U+04FF, ' .  // Cyrillic (Russian, Ukrainian, Bulgarian, Serbian, etc.)
			'U+0500..U+052F, ' .  // Cyrillic Supplement
			'U+0590..U+05FF, ' .  // Hebrew
			'U+0600..U+06FF, ' .  // Arabic and Persian
			'U+0750..U+077F, ' .  // Arabic Supplement
			'U+0900..U+097F, ' .  // Devanagari (Hindi, Sanskrit, Marathi, Nepali)
			'U+0980..U+09FF, ' .  // Bengali
			'U+0A00..U+0A7F, ' .  // Gurmukhi (Punjabi)
			'U+0A80..U+0AFF, ' .  // Gujarati
			'U+0B00..U+0B7F, ' .  // Oriya
			'U+0B80..U+0BFF, ' .  // Tamil
			'U+0C00..U+0C7F, ' .  // Telugu
			'U+0C80..U+0CFF, ' .  // Kannada
			'U+0D00..U+0D7F, ' .  // Malayalam
			'U+0E00..U+0E7F, ' .  // Thai
			'U+0E80..U+0EFF, ' .  // Lao
			'U+1000..U+109F, ' .  // Myanmar (Burmese)
			'U+10A0..U+10FF, ' .  // Georgian
			'U+1100..U+11FF, ' .  // Hangul Jamo (Korean)
			'U+1E00..U+1EFF, ' .  // Latin Extended Additional (Vietnamese)
			'U+2000..U+206F, ' .  // General Punctuation
			'U+FB50..U+FDFF, ' .  // Arabic Presentation Forms-A
			'U+FE70..U+FEFF, ' .  // Arabic Presentation Forms-B
			'U+200C, ' .          // Zero-width non-joiner (ZWNJ) - critical for Persian/Arabic
			'U+200D';             // Zero-width joiner (ZWJ)

		// CJK characters (Chinese, Japanese, Korean) handled separately via ngrams
		$ngram_chars = 'U+3000..U+9FFF, U+AC00..U+D7AF, U+FF00..U+FFEF';

		$create_query = "CREATE TABLE {$index_name} (" . implode( ', ', $all_fields ) . ") " .
			"charset_table='{$charset_table}' " .
			"min_infix_len='2' " .              // Minimum 2 chars for infix search
			"morphology='stem_en' " .           // English stemming (skill->skills, dress->dresses)
			"expand_keywords='1' " .            // Enable infix expansion with morphology
			"ngram_len='1' " .                   // Character-level ngrams for CJK
			"ngram_chars='{$ngram_chars}'";      // CJK Unicode blocks

		return (bool) $this->query( $create_query );
	}

	/**
	 * Insert document into index
	 *
	 * @param string $index_name Index name
	 * @param int $id Document ID
	 * @param array $data Document data
	 * @return bool
	 */
	public function insert( $index_name, $id, $data ) {
		$fields = array();
		$values = array();

		// Text fields that should always be quoted (even if numeric)
		$text_fields = array( 'title', 'content', 'excerpt', 'sku', 'short_description',
							  'attributes', 'variations', 'categories', 'tags', 'author',
							  'post_type', 'post_status', 'stock_status', 'visibility' );

		foreach ( $data as $field => $value ) {
			$fields[] = $field;

			if ( is_array( $value ) ) {
				// Multi-valued attribute (MVA) - empty arrays should be ()
				if ( empty( $value ) ) {
					$values[] = '()';
				} else {
					$values[] = '(' . implode( ',', array_map( 'intval', $value ) ) . ')';
				}
			} elseif ( is_null( $value ) || $value === '' ) {
				// Empty strings and nulls should be empty string for text fields
				$values[] = "''";
			} elseif ( in_array( $field, $text_fields ) ) {
				// Text fields must be quoted even if they look numeric (like SKU)
				$values[] = "'" . $this->escape( $value ) . "'";
			} elseif ( is_numeric( $value ) ) {
				// Numeric values (including 0) for non-text fields
				$values[] = $value;
			} else {
				// Regular strings
				$values[] = "'" . $this->escape( $value ) . "'";
			}
		}

		$query = sprintf(
			"INSERT INTO %s (id, %s) VALUES (%d, %s)",
			$index_name,
			implode( ', ', $fields ),
			$id,
			implode( ', ', $values )
		);

		return (bool) $this->query( $query );
	}

	/**
	 * Replace document in index
	 *
	 * @param string $index_name Index name
	 * @param int $id Document ID
	 * @param array $data Document data
	 * @return bool
	 */
	public function replace( $index_name, $id, $data ) {
		$fields = array();
		$values = array();

		// Text fields that should always be quoted (even if numeric)
		$text_fields = array( 'title', 'content', 'excerpt', 'sku', 'short_description',
							  'attributes', 'variations', 'categories', 'tags', 'author',
							  'post_type', 'post_status', 'stock_status', 'visibility',
							  // User/customer fields
							  'user_login', 'user_email', 'user_phone', 'billing_address', 'user_role',
							  // Order fields
							  'order_number', 'customer_name', 'customer_email', 'customer_phone',
							  'billing_company', 'shipping_address', 'order_items', 'order_notes',
							  'order_status', 'payment_method' );

		foreach ( $data as $field => $value ) {
			$fields[] = $field;

			if ( is_array( $value ) ) {
				// Multi-valued attribute (MVA) - empty arrays should be ()
				if ( empty( $value ) ) {
					$values[] = '()';
				} else {
					$values[] = '(' . implode( ',', array_map( 'intval', $value ) ) . ')';
				}
			} elseif ( is_null( $value ) || $value === '' ) {
				// Empty strings and nulls should be empty string for text fields
				$values[] = "''";
			} elseif ( in_array( $field, $text_fields ) ) {
				// Text fields must be quoted even if they look numeric (like SKU)
				$values[] = "'" . $this->escape( $value ) . "'";
			} elseif ( is_numeric( $value ) ) {
				// Numeric values (including 0) for non-text fields
				$values[] = $value;
			} else {
				// Regular strings
				$values[] = "'" . $this->escape( $value ) . "'";
			}
		}

		$query = sprintf(
			"REPLACE INTO %s (id, %s) VALUES (%d, %s)",
			$index_name,
			implode( ', ', $fields ),
			$id,
			implode( ', ', $values )
		);

		return (bool) $this->query( $query );
	}

	/**
	 * Delete document from index
	 *
	 * @param string $index_name Index name
	 * @param int $id Document ID
	 * @return bool
	 */
	public function delete( $index_name, $id ) {
		$query = sprintf(
			"DELETE FROM %s WHERE id = %d",
			$index_name,
			$id
		);

		return (bool) $this->query( $query );
	}

	/**
	 * Bulk insert documents
	 *
	 * @param string $index_name Index name
	 * @param array $documents Array of documents [id => data]
	 * @return bool
	 */
	public function bulk_insert( $index_name, $documents ) {
		if ( empty( $documents ) ) {
			return true;
		}

		// Use BEGIN/COMMIT transaction for fast batch inserts
		// Can't use single multi-row INSERT because products have different dynamic attribute fields
		// (pa_color_ids, pa_brand_ids, etc.) which causes column mismatch errors
		$this->query( 'BEGIN' );

		$success = true;
		foreach ( $documents as $id => $data ) {
			if ( ! $this->replace( $index_name, $id, $data ) ) {
				$success = false;
				break;
			}
		}

		if ( $success ) {
			$this->query( 'COMMIT' );
		} else {
			$this->query( 'ROLLBACK' );
		}

		return $success;
	}

	/**
	 * Truncate index
	 *
	 * @param string $index_name Index name
	 * @return bool
	 */
	public function truncate( $index_name ) {
		return (bool) $this->query( "TRUNCATE TABLE {$index_name}" );
	}

	/**
	 * Optimize index
	 *
	 * @param string $index_name Index name
	 * @return bool
	 */
	public function optimize( $index_name ) {
		return (bool) $this->query( "OPTIMIZE TABLE {$index_name}" );
	}

	/**
	 * Get index status
	 *
	 * @param string $index_name Index name
	 * @return array|false
	 */
	public function get_index_status( $index_name ) {
		$result = $this->query( "SHOW INDEX {$index_name} STATUS" );

		if ( ! $result ) {
			return false;
		}

		$status = array();
		while ( $row = $result->fetch_assoc() ) {
			$status[ $row['Variable_name'] ] = $row['Value'];
		}

		return $status;
	}

	/**
	 * Get document count
	 *
	 * @param string $index_name Index name
	 * @return int
	 */
	public function count_documents( $index_name ) {
		$result = $this->query( "SELECT COUNT(*) as total FROM {$index_name}" );

		if ( ! $result ) {
			return 0;
		}

		$row = $result->fetch_assoc();
		return (int) $row['total'];
	}

	/**
	 * Get index size across all configured post types
	 *
	 * @return array Array with 'total_bytes', 'total_mb', 'by_type' keys
	 */
	public function get_index_size() {
		$index_name = \MantiLoad\MantiLoad::get_option( 'index_name', \MantiLoad\MantiLoad::get_default_index_name() );

		// Get index status which includes ram_bytes and disk_bytes
		$status = $this->get_index_status( $index_name );

		if ( $status ) {
			// Use ram_bytes + disk_bytes for total size (RAM is where most data is before optimization)
			$ram_bytes = isset( $status['ram_bytes'] ) ? (int) $status['ram_bytes'] : 0;
			$disk_bytes = isset( $status['disk_bytes'] ) ? (int) $status['disk_bytes'] : 0;
			$total_bytes = $ram_bytes + $disk_bytes;
			$documents = isset( $status['indexed_documents'] ) ? (int) $status['indexed_documents'] : 0;

			return array(
				'total_bytes' => $total_bytes,
				'total_mb'    => round( $total_bytes / 1024 / 1024, 2 ),
				'documents'   => $documents,
			);
		}

		return array(
			'total_bytes' => 0,
			'total_mb'    => 0,
			'documents'   => 0,
		);
	}

	/**
	 * Check if index exists
	 *
	 * @param string $index_name Index name
	 * @return bool
	 */
	public function index_exists( $index_name ) {
		$result = $this->query( "SHOW TABLES LIKE '{$index_name}'" );
		return $result && $result->num_rows > 0;
	}

	/**
	 * Get last error
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get last query execution time
	 *
	 * @return float Time in milliseconds
	 */
	public function get_query_time() {
		return $this->query_time;
	}

	/**
	 * Get the underlying mysqli connection
	 *
	 * @return \mysqli|null
	 */
	public function get_connection() {
		if ( ! $this->connection ) {
			$this->connect();
		}
		return $this->connection;
	}

	/**
	 * Check if Manticore is available and healthy
	 *
	 * @return bool
	 */
	public function is_healthy() {
		// Try to connect
		if ( ! $this->connect() ) {
			return false;
		}

		// Try a simple test query
		try {
			$result = $this->query( 'SHOW TABLES' );
			return $result !== false;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Get connection status with details
	 *
	 * @return array Status information
	 */
	public function get_status() {
		$status = array(
			'connected' => false,
			'host'      => $this->get_host(),
			'port'      => $this->get_port(),
			'error'     => '',
			'tables'    => array(),
		);

		if ( ! $this->connect() ) {
			$status['error'] = $this->last_error ?: 'Unable to connect to Manticore Search';
			return $status;
		}

		$status['connected'] = true;

		// Get list of tables
		try {
			$result = $this->query( 'SHOW TABLES' );
			if ( $result ) {
				while ( $row = $result->fetch_assoc() ) {
					$status['tables'][] = reset( $row );
				}
			}
		} catch ( \Exception $e ) {
			$status['error'] = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Close connection
	 */
	public function close() {
		if ( $this->connection ) {
			$this->connection->close();
			$this->connection = null;
		}
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->close();
	}
}
