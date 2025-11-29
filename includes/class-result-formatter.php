<?php
namespace MantiLoad;
defined( 'ABSPATH' ) || exit;

class Result_Formatter {
	public static function format_results( $results ) {
		return $results;
	}
	
	public static function highlight_terms( $text, $query ) {
		$words = explode( ' ', $query );
		foreach ( $words as $word ) {
			$text = preg_replace( '/(' . preg_quote( $word, '/' ) . ')/i', '<mark>$1</mark>', $text );
		}
		return $text;
	}
}
