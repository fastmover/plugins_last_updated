<?php
/**
 * Plugin Name: Test Helper - Fake WordPress.org ages
 * Description: Test fixture only. Answers WordPress.org plugin lookups for the plu-test-age-* stubs with fixed ages, so every color band can be tested without depending on real plugins' release dates.
 * Version: 0.0.1
 */

add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		$ages = array(
			'plu-test-age-fresh'     => '-10 days',   // green
			'plu-test-age-8-months'  => '-8 months',  // yellow
			'plu-test-age-18-months' => '-18 months', // orange
			'plu-test-age-3-years'   => '-3 years',   // red
		);

		if ( 'plugin_information' !== $action || ! isset( $args->slug, $ages[ $args->slug ] ) ) {
			return $result;
		}

		// Same format WordPress.org uses, e.g. "2025-04-04 1:45am GMT".
		return (object) array(
			'slug'         => $args->slug,
			'last_updated' => gmdate( 'Y-m-d g:ia', strtotime( $ages[ $args->slug ] ) ) . ' GMT',
		);
	},
	10,
	3
);
