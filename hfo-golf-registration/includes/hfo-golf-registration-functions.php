<?php
/**
 * Shared helper functions for HFO Golf Registration.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes guest names to one sanitized, unique name per line.
 *
 * Names may be separated by commas or line breaks. Spaces are deliberately not
 * separators because a guest's full name can contain spaces.
 *
 * @param mixed $value Raw guest names.
 * @return string Newline-separated guest names.
 */
function hfo_golf_normalize_guest_names( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value   = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	$entries = preg_split( '/[\n,]+/', $value );
	$names   = array();
	$seen    = array();

	foreach ( $entries as $entry ) {
		$name = trim( sanitize_text_field( $entry ) );
		if ( '' === $name ) {
			continue;
		}

		$duplicate_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
		if ( isset( $seen[ $duplicate_key ] ) ) {
			continue;
		}

		$seen[ $duplicate_key ] = true;
		$names[]                = $name;
	}

	return implode( "\n", $names );
}
